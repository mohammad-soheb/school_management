<?php

namespace App\Services\Communication;

use App\Models\Communication\Homework;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Who may set homework for which class / section / subject.
 *
 *   homework.manage_all (or School Admin) -> any class, section and subject offered in the class
 *   homework.manage                       -> only where the teacher has a teacher_assignments row WITH a subject in
 *                                            that academic year: a section assignment covers that section; a
 *                                            whole-class assignment (section NULL) covers every section; homework for
 *                                            the whole class needs a whole-class assignment or one for every section.
 *
 * Class-teacher rows without a subject do not grant homework rights (same rule as AssignmentScope::subjectIds()).
 * Registered as a scoped binding (one instance per request), so the per-user memo never crosses requests.
 */
class HomeworkAccess
{
    /** @var array<string, Collection<int, TeacherAssignment>> */
    private array $memo = [];

    public function __construct(private readonly TenantContext $context) {}

    public function unrestricted(User $user): bool
    {
        return Gate::forUser($user)->allows('homework.manage_all');
    }

    /** Holds any homework write permission (record-level checks still apply). */
    public function canAuthor(User $user): bool
    {
        return $this->unrestricted($user) || Gate::forUser($user)->allows('homework.manage');
    }

    public function allows(User $user, int $yearId, int $classId, ?int $sectionId, int $subjectId): bool
    {
        if ($this->unrestricted($user)) {
            return true;
        }

        if (! Gate::forUser($user)->allows('homework.manage')) {
            return false;
        }

        $rows = $this->assignments($user, $yearId)
            ->filter(fn (TeacherAssignment $a) => (int) $a->school_class_id === $classId && (int) $a->subject_id === $subjectId);

        if ($rows->isEmpty()) {
            return false;
        }

        if ($rows->contains(fn (TeacherAssignment $a) => $a->section_id === null)) {
            return true;
        }

        $taught = $rows->pluck('section_id')->map(fn ($id) => (int) $id)->all();

        if ($sectionId !== null) {
            return in_array($sectionId, $taught, true);
        }

        $all = Section::query()->where('school_class_id', $classId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $all !== [] && array_diff($all, $taught) === [];
    }

    public function allowsRecord(User $user, Homework $homework): bool
    {
        return $this->allows($user, (int) $homework->academic_year_id, (int) $homework->school_class_id, $homework->section_id === null ? null : (int) $homework->section_id, (int) $homework->subject_id);
    }

    /**
     * Restrict a homework query to the teacher's own classes/subjects (plus anything they created).
     * No-op for unrestricted users.
     *
     * @param  Builder<Homework>  $query
     */
    public function scopeToTeacher(Builder $query, User $user, int $yearId): void
    {
        if ($this->unrestricted($user)) {
            return;
        }

        $rows = $this->assignments($user, $yearId);

        $query->where(function (Builder $q) use ($rows, $user) {
            $q->where('created_by', $user->getKey());

            foreach ($rows as $a) {
                $q->orWhere(function (Builder $q) use ($a) {
                    $q->where('school_class_id', $a->school_class_id)->where('subject_id', $a->subject_id);
                    if ($a->section_id !== null) {
                        $q->where(fn (Builder $q) => $q->whereNull('section_id')->orWhere('section_id', $a->section_id));
                    }
                });
            }
        });
    }

    /**
     * What the create/edit form may offer, for the Alpine pickers. The server re-checks every submission.
     *
     * @return list<array{id: int, name: string, sections: list<array{id: int, name: string, subjects: list<int>}>, whole_class_subjects: list<int>}>
     */
    public function formOptions(User $user, int $yearId): array
    {
        $classes = SchoolClass::query()->with('sections')->orderBy('sort_order')->orderBy('name')->get();
        $offered = $this->offeredSubjects();
        $unrestricted = $this->unrestricted($user);
        $out = [];

        foreach ($classes as $class) {
            $classSubjects = $offered[$class->id] ?? $offered[0];
            $sections = [];
            $whole = [];

            foreach ($class->sections as $section) {
                $subjects = $unrestricted
                    ? $classSubjects
                    : array_values(array_filter($classSubjects, fn (int $s) => $this->allows($user, $yearId, $class->id, $section->id, $s)));

                if ($subjects !== []) {
                    $sections[] = ['id' => $section->id, 'name' => $section->name, 'subjects' => $subjects];
                }
            }

            $whole = $unrestricted
                ? $classSubjects
                : array_values(array_filter($classSubjects, fn (int $s) => $this->allows($user, $yearId, $class->id, null, $s)));

            if ($sections !== [] || $whole !== []) {
                $out[] = ['id' => $class->id, 'name' => $class->name, 'sections' => $sections, 'whole_class_subjects' => $whole];
            }
        }

        return $out;
    }

    /**
     * Subject ids offered per class (from class_subjects); key 0 holds every active subject, used for classes whose
     * subjects are not configured yet.
     *
     * @return array<int, list<int>>
     */
    public function offeredSubjects(): array
    {
        $active = Subject::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $map = [0 => $active];

        foreach (ClassSubject::query()->orderBy('sort_order')->get(['school_class_id', 'subject_id']) as $row) {
            if (in_array((int) $row->subject_id, $active, true)) {
                $map[(int) $row->school_class_id][] = (int) $row->subject_id;
            }
        }

        return $map;
    }

    public function subjectOfferedInClass(int $classId, int $subjectId): bool
    {
        $offered = $this->offeredSubjects();

        return in_array($subjectId, $offered[$classId] ?? $offered[0], true);
    }

    /** @return Collection<int, TeacherAssignment> rows with a subject, for the user's staff profile in that year */
    private function assignments(User $user, int $yearId): Collection
    {
        $key = $this->context->id().':'.$user->getKey().':'.$yearId;

        return $this->memo[$key] ??= TeacherAssignment::query()
            ->where('academic_year_id', $yearId)
            ->whereNotNull('subject_id')
            ->whereHas('staffProfile', fn ($q) => $q->where('user_id', $user->getKey()))
            ->get(['school_class_id', 'section_id', 'subject_id']);
    }
}
