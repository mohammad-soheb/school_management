<?php

namespace App\Services\SchoolSetup;

use App\Enums\SchoolSetup\SubjectType;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\Subject;
use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subjects (FR-202). Code is unique per school (request + database), type is scholastic / co-scholastic, and a subject
 * that is taught in a class or assigned to a teacher is never deleted: it can be deactivated instead, which hides it
 * from pickers for new records while history keeps pointing at it.
 */
class SubjectService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{q?: ?string, type?: ?string, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Subject>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $type = SubjectType::tryFrom((string) ($filters['type'] ?? ''));
        $status = $filters['status'] ?? null;

        return Subject::query()
            ->withCount('classSubjects')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('code', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active subjects for pickers (class-subject mapping, exams ...).
     *
     * @return Collection<int, Subject>
     */
    public function active(): Collection
    {
        return Subject::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @param  array{name: string, code?: ?string, type: string, sort_order?: ?int, is_active?: bool}  $data */
    public function create(array $data, User $actor): Subject
    {
        return DB::transaction(function () use ($data, $actor): Subject {
            $subject = Subject::create($this->attributes($data) + ['is_active' => (bool) ($data['is_active'] ?? true)]);
            $this->audit->record('subject.created', $subject, new: $this->snapshot($subject), actor: $actor);

            return $subject;
        });
    }

    /** @param  array{name: string, code?: ?string, type: string, sort_order?: ?int, is_active?: bool}  $data */
    public function update(Subject $subject, array $data, User $actor): Subject
    {
        return DB::transaction(function () use ($subject, $data, $actor): Subject {
            $before = $this->snapshot($subject);
            $subject->fill($this->attributes($data));
            if (array_key_exists('is_active', $data)) {
                $subject->is_active = (bool) $data['is_active'];
            }
            $subject->save();

            [$old, $new] = $this->audit->diff($before, $this->snapshot($subject));
            if ($new !== []) {
                $this->audit->record('subject.updated', $subject, $old, $new, actor: $actor);
            }

            return $subject;
        });
    }

    public function setActive(Subject $subject, bool $active, User $actor): Subject
    {
        if ($subject->is_active === $active) {
            return $subject;
        }

        $subject->forceFill(['is_active' => $active])->save();
        $this->audit->record($active ? 'subject.activated' : 'subject.deactivated', $subject, new: ['name' => $subject->name], actor: $actor);

        return $subject;
    }

    /** Delete a subject that was never used anywhere. */
    public function delete(Subject $subject, User $actor): void
    {
        $classes = ClassSubject::query()->where('subject_id', $subject->getKey())->count();
        $assignments = TeacherAssignment::query()->where('subject_id', $subject->getKey())->count();

        if ($classes > 0 || $assignments > 0) {
            $uses = array_filter([
                $classes > 0 ? "taught in {$classes} ".($classes === 1 ? 'class' : 'classes') : null,
                $assignments > 0 ? "{$assignments} teacher ".($assignments === 1 ? 'assignment' : 'assignments') : null,
            ]);

            throw ValidationException::withMessages(['subject' => "{$subject->name} is in use (".implode(' and ', $uses).') and cannot be deleted. Deactivate it instead: history is kept and it is hidden for new records.']);
        }

        try {
            DB::transaction(function () use ($subject, $actor): void {
                $subject->delete();
                $this->audit->record('subject.deleted', $subject, $this->snapshot($subject), actor: $actor);
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
                throw $e;
            }

            throw ValidationException::withMessages(['subject' => "{$subject->name} is still referenced by other records (exams, marks ...) and cannot be deleted. Deactivate it instead."]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, code: ?string, type: string, sort_order: int}
     */
    private function attributes(array $data): array
    {
        $code = trim((string) ($data['code'] ?? ''));

        return [
            'name' => trim((string) $data['name']),
            'code' => $code === '' ? null : strtoupper($code),
            'type' => SubjectType::from($data['type'])->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Subject $subject): array
    {
        return [
            'name' => $subject->name,
            'code' => $subject->code,
            'type' => $subject->type instanceof SubjectType ? $subject->type->value : $subject->type,
            'sort_order' => (int) $subject->sort_order,
            'is_active' => (bool) $subject->is_active,
        ];
    }
}
