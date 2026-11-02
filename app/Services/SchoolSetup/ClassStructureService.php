<?php

namespace App\Services\SchoolSetup;

use App\Enums\Staff\StaffStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Classes, sections and the subjects taught in each class (FR-202).
 *
 * Rules: a class's "promotes to" chain may not loop back on itself; a section name is unique within its class; a
 * class teacher must be an active staff member (validated in the request). Anything that already has history
 * (enrollments, teacher assignments or rows of other modules) is never deleted: the caller gets a message suggesting
 * deactivation, which hides it from pickers but keeps every record pointing at it.
 */
class ClassStructureService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, SchoolClass> every class in promotion order, with counts for the list page */
    public function classes(): Collection
    {
        return SchoolClass::query()
            ->with('nextClass:id,name')
            ->withCount(['sections', 'subjects'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * id => name for selects. Other modules should use this so inactive classes are not offered for new records.
     *
     * @return array<int, string>
     */
    public function classOptions(bool $activeOnly = true, ?int $except = null): array
    {
        return SchoolClass::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Active staff who can be picked as a class teacher: id => "Name (EMP-ID)". $include keeps a section's current
     * class teacher in the list even if they have since become inactive, so editing the section does not fail.
     *
     * @return array<int, string>
     */
    public function teacherOptions(?int $include = null): array
    {
        return StaffProfile::query()
            ->where(fn ($q) => $q->where('status', StaffStatus::Active->value)
                ->when($include !== null, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_id'])
            ->mapWithKeys(fn (StaffProfile $s): array => [$s->id => $s->fullName().($s->employee_id ? " ({$s->employee_id})" : '')])
            ->all();
    }

    /** Load what the class page shows: sections (with their class teacher) and the subject list. */
    public function loadForShow(SchoolClass $class): SchoolClass
    {
        return $class->load([
            'nextClass:id,name',
            'subjects',
            'sections' => fn ($q) => $q->with('classTeacher:id,first_name,last_name,employee_id'),
        ]);
    }

    /**
     * Active students per section of $class in the current academic year: section_id => count.
     *
     * @return array<int, int>
     */
    public function sectionStudentCounts(SchoolClass $class): array
    {
        $yearId = $this->context->academicYear()?->getKey();

        if ($yearId === null) {
            return [];
        }

        return StudentEnrollment::query()
            ->where('academic_year_id', $yearId)
            ->where('school_class_id', $class->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->groupBy('section_id')
            ->selectRaw('section_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'section_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /** @param  array{name: string, code?: ?string, sort_order?: ?int, next_class_id?: ?int, is_active?: bool}  $data */
    public function createClass(array $data, User $actor): SchoolClass
    {
        return DB::transaction(function () use ($data, $actor): SchoolClass {
            $class = new SchoolClass;
            $class->fill($this->classAttributes($data));
            $class->forceFill(['is_active' => (bool) ($data['is_active'] ?? true)]);
            $class->save();

            $this->audit->record('class.created', $class, new: $this->classSnapshot($class), actor: $actor);

            return $class;
        });
    }

    /** @param  array{name: string, code?: ?string, sort_order?: ?int, next_class_id?: ?int, is_active?: bool}  $data */
    public function updateClass(SchoolClass $class, array $data, User $actor): SchoolClass
    {
        return DB::transaction(function () use ($class, $data, $actor): SchoolClass {
            // Serialise structure edits so two concurrent "promotes to" changes cannot build a loop between them.
            SchoolClass::query()->lockForUpdate()->get(['id']);

            $attributes = $this->classAttributes($data);
            $this->assertNoPromotionCycle($class, $attributes['next_class_id']);

            $before = $this->classSnapshot($class);
            $class->fill($attributes);
            if (array_key_exists('is_active', $data)) {
                $class->forceFill(['is_active' => (bool) $data['is_active']]);
            }
            $class->save();

            [$old, $new] = $this->audit->diff($before, $this->classSnapshot($class));
            if ($new !== []) {
                $this->audit->record('class.updated', $class, $old, $new, actor: $actor);
            }

            return $class;
        });
    }

    public function setClassActive(SchoolClass $class, bool $active, User $actor): SchoolClass
    {
        if ((bool) $class->is_active === $active) {
            return $class;
        }

        $class->forceFill(['is_active' => $active])->save();
        $this->audit->record($active ? 'class.activated' : 'class.deactivated', $class, new: ['name' => $class->name], actor: $actor);

        return $class;
    }

    /** Delete a class that was never used. Its unused sections and subject list go with it. */
    public function deleteClass(SchoolClass $class, User $actor): void
    {
        $enrollments = StudentEnrollment::query()->where('school_class_id', $class->getKey())->count();
        $assignments = TeacherAssignment::query()->where('school_class_id', $class->getKey())->count();

        if ($enrollments > 0 || $assignments > 0) {
            throw ValidationException::withMessages(['class' => $this->inUseMessage($class->name, $enrollments, $assignments)]);
        }

        $this->guardForeignKeys($class->name, function () use ($class, $actor): void {
            DB::transaction(function () use ($class, $actor): void {
                SchoolClass::query()->where('next_class_id', $class->getKey())->update(['next_class_id' => null]);
                ClassSubject::query()->where('school_class_id', $class->getKey())->delete();
                Section::query()->where('school_class_id', $class->getKey())->delete();
                $class->delete();

                $this->audit->record('class.deleted', $class, $this->classSnapshot($class), actor: $actor);
            });
        });
    }

    /** @param  array{name: string, capacity?: ?int, class_teacher_id?: ?int, sort_order?: ?int}  $data */
    public function createSection(SchoolClass $class, array $data, User $actor): Section
    {
        return DB::transaction(function () use ($class, $data, $actor): Section {
            $section = new Section;
            $section->fill($this->sectionAttributes($data) + ['school_class_id' => $class->getKey()]);
            $section->forceFill(['is_active' => true]);
            $section->save();

            $this->audit->record('section.created', $section, new: $this->sectionSnapshot($section, $class), actor: $actor);

            return $section;
        });
    }

    /** @param  array{name: string, capacity?: ?int, class_teacher_id?: ?int, sort_order?: ?int, is_active?: bool}  $data */
    public function updateSection(Section $section, array $data, User $actor): Section
    {
        $class = $section->schoolClass()->firstOrFail();

        return DB::transaction(function () use ($section, $class, $data, $actor): Section {
            $before = $this->sectionSnapshot($section, $class);
            $section->fill($this->sectionAttributes($data));
            if (array_key_exists('is_active', $data)) {
                $section->forceFill(['is_active' => (bool) $data['is_active']]);
            }
            $section->save();

            [$old, $new] = $this->audit->diff($before, $this->sectionSnapshot($section, $class));
            if ($new !== []) {
                $this->audit->record('section.updated', $section, $old, $new, actor: $actor);
            }

            return $section;
        });
    }

    public function setSectionActive(Section $section, bool $active, User $actor): Section
    {
        if ((bool) $section->is_active === $active) {
            return $section;
        }

        $section->forceFill(['is_active' => $active])->save();
        $this->audit->record($active ? 'section.activated' : 'section.deactivated', $section, new: ['name' => $section->name, 'school_class_id' => $section->school_class_id], actor: $actor);

        return $section;
    }

    public function deleteSection(Section $section, User $actor): void
    {
        $class = $section->schoolClass()->firstOrFail();
        $label = "{$class->name} - {$section->name}";
        $enrollments = StudentEnrollment::query()->where('section_id', $section->getKey())->count();
        $assignments = TeacherAssignment::query()->where('section_id', $section->getKey())->count();

        if ($enrollments > 0 || $assignments > 0) {
            throw ValidationException::withMessages(['section' => $this->inUseMessage("Section {$label}", $enrollments, $assignments)]);
        }

        $this->guardForeignKeys("Section {$label}", function () use ($section, $class, $actor): void {
            DB::transaction(function () use ($section, $class, $actor): void {
                $section->delete();
                $this->audit->record('section.deleted', $section, $this->sectionSnapshot($section, $class), actor: $actor);
            });
        });
    }

    /**
     * Replace the list of subjects taught in a class.
     *
     * @param  list<array{subject_id: int, is_optional?: bool, sort_order?: ?int}>  $items
     */
    public function syncSubjects(SchoolClass $class, array $items, User $actor): void
    {
        $wanted = [];
        foreach (array_values($items) as $position => $item) {
            $wanted[(int) $item['subject_id']] = [
                'is_optional' => (bool) ($item['is_optional'] ?? false),
                'sort_order' => (int) ($item['sort_order'] ?? ($position + 1)),
            ];
        }

        $this->guardForeignKeys("A subject of {$class->name}", function () use ($class, $wanted, $actor): void {
            DB::transaction(function () use ($class, $wanted, $actor): void {
                $existing = ClassSubject::query()->where('school_class_id', $class->getKey())->lockForUpdate()->get()->keyBy('subject_id');
                $before = $existing->map(fn (ClassSubject $row): array => ['optional' => $row->is_optional, 'order' => $row->sort_order])->all();

                $removed = $existing->keys()->diff(array_keys($wanted))->all();
                if ($removed !== []) {
                    $assigned = TeacherAssignment::query()
                        ->where('school_class_id', $class->getKey())
                        ->whereIn('subject_id', $removed)
                        ->count();

                    if ($assigned > 0) {
                        throw ValidationException::withMessages(['subjects' => 'Teachers are assigned to a subject you removed from this class. Change those teacher assignments first.']);
                    }

                    ClassSubject::query()->where('school_class_id', $class->getKey())->whereIn('subject_id', $removed)->delete();
                }

                foreach ($wanted as $subjectId => $pivot) {
                    $row = $existing->get($subjectId) ?? new ClassSubject(['school_class_id' => $class->getKey(), 'subject_id' => $subjectId]);
                    $row->fill($pivot)->save();
                }

                $after = array_map(fn (array $p): array => ['optional' => $p['is_optional'], 'order' => $p['sort_order']], $wanted);
                if ($before != $after) {
                    $this->audit->record('class.subjects_changed', $class, ['subjects' => $before], ['subjects' => $after], actor: $actor);
                }
            });
        });
    }

    /**
     * Refuse a "promotes to" target that leads back to $class (directly or through a chain).
     */
    public function assertNoPromotionCycle(SchoolClass $class, ?int $nextClassId): void
    {
        if ($nextClassId === null) {
            return;
        }

        if ($nextClassId === $class->getKey()) {
            throw ValidationException::withMessages(['next_class_id' => 'A class cannot promote to itself.']);
        }

        $links = SchoolClass::query()->pluck('next_class_id', 'id')->all();
        $links[$class->getKey()] = $nextClassId;
        $seen = [];

        for ($cursor = $nextClassId; $cursor !== null; $cursor = $links[$cursor] ?? null) {
            if ($cursor === $class->getKey() || isset($seen[$cursor])) {
                throw ValidationException::withMessages(['next_class_id' => 'That choice would create a loop: the chain of "promotes to" classes leads back to this class.']);
            }
            $seen[$cursor] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, code: ?string, sort_order: int, next_class_id: ?int}
     */
    private function classAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => ($data['code'] ?? null) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'next_class_id' => isset($data['next_class_id']) ? (int) $data['next_class_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, capacity: ?int, class_teacher_id: ?int, sort_order: int}
     */
    private function sectionAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
            'class_teacher_id' => isset($data['class_teacher_id']) ? (int) $data['class_teacher_id'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function classSnapshot(SchoolClass $class): array
    {
        return [
            'name' => $class->name,
            'code' => $class->code,
            'sort_order' => (int) $class->sort_order,
            'next_class_id' => $class->next_class_id,
            'is_active' => (bool) $class->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function sectionSnapshot(Section $section, SchoolClass $class): array
    {
        return [
            'class' => $class->name,
            'name' => $section->name,
            'capacity' => $section->capacity,
            'class_teacher_id' => $section->class_teacher_id,
            'sort_order' => (int) $section->sort_order,
            'is_active' => (bool) ($section->is_active ?? true),
        ];
    }

    private function inUseMessage(string $label, int $enrollments, int $assignments): string
    {
        $uses = array_filter([
            $enrollments > 0 ? $enrollments.' student '.($enrollments === 1 ? 'enrollment' : 'enrollments') : null,
            $assignments > 0 ? $assignments.' teacher '.($assignments === 1 ? 'assignment' : 'assignments') : null,
        ]);

        return "{$label} is in use (".implode(' and ', $uses).') and cannot be deleted. Deactivate it instead: history is kept and it is hidden for new records.';
    }

    /** Run $write; a foreign-key refusal from another module's table becomes a friendly "in use" message. */
    private function guardForeignKeys(string $label, Closure $write): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
                throw $e;
            }

            throw ValidationException::withMessages(['record' => "{$label} is still referenced by other records (attendance, fees, exams ...) and cannot be removed. Deactivate it instead."]);
        }
    }
}
