<?php

namespace App\Services\Communication;

use App\Enums\Communication\HomeworkStatus;
use App\Models\Communication\Homework;
use App\Models\Documents\StoredFile;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\User;
use App\Services\Communication\Concerns\StoresAttachments;
use App\Services\Documents\PrivateFileService;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Homework workflows (FR-505, FR-508): set, edit, archive/restore, attachments, and the staff list with filters.
 * Teacher scoping is decided by HomeworkAccess; every write re-checks it here, whatever the form offered.
 */
class HomeworkService
{
    use StoresAttachments;

    /** Columns the list may be sorted by (never order by raw request input). */
    public const SORTABLE = ['due_on', 'assigned_on', 'title'];

    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly PrivateFileService $files,
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array{q?: ?string, class_id?: ?int, section_id?: ?int, subject_id?: ?int, due?: ?string, status?: ?string, mine?: bool, sort?: ?string, dir?: ?string}  $filters
     * @return LengthAwarePaginator<int, Homework>
     */
    public function paginate(array $filters, User $user, int $perPage = 20): LengthAwarePaginator
    {
        $year = $this->context->academicYear();
        $today = school_today()->toDateString();
        $canSeeArchived = $this->access->canAuthor($user);
        $status = ($filters['status'] ?? null) === HomeworkStatus::Archived->value && $canSeeArchived ? HomeworkStatus::Archived : HomeworkStatus::Active;

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : null;
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Homework::query()
            ->with(['schoolClass', 'section', 'subject', 'creator'])
            ->withCount('attachments')
            ->where('status', $status->value)
            ->when($year, fn (Builder $q) => $q->where('academic_year_id', $year->getKey()))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['class_id'] ?? null, fn (Builder $q, int $id) => $q->where('school_class_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $q, int $id) => $q->where(fn (Builder $q) => $q->where('section_id', $id)->orWhereNull('section_id')))
            ->when($filters['subject_id'] ?? null, fn (Builder $q, int $id) => $q->where('subject_id', $id))
            ->when(($filters['due'] ?? null) === 'week', fn (Builder $q) => $q->whereBetween('due_on', $this->thisWeek()))
            ->when(($filters['due'] ?? null) === 'today', fn (Builder $q) => $q->where('due_on', $today))
            ->when(($filters['due'] ?? null) === 'upcoming', fn (Builder $q) => $q->where('due_on', '>=', $today))
            ->when(($filters['due'] ?? null) === 'overdue', fn (Builder $q) => $q->where('due_on', '<', $today))
            // Archived homework is listed only to the people who could restore it; "mine" narrows a teacher's list.
            ->when((($filters['mine'] ?? false) || $status === HomeworkStatus::Archived) && $year, fn (Builder $q) => $this->access->scopeToTeacher($q, $user, $year->getKey()))
            ->when($sort, fn (Builder $q) => $q->orderBy($sort, $dir), fn (Builder $q) => $q->orderByDesc('assigned_on'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active homework due this school week, for the dashboard. Teachers see their own classes/subjects.
     *
     * @return Collection<int, Homework>
     */
    public function dueThisWeek(User $user, int $limit = 6): Collection
    {
        $year = $this->context->academicYear();
        if ($year === null) {
            return new Collection;
        }

        return Homework::query()
            ->active()
            ->with(['schoolClass', 'section', 'subject'])
            ->where('academic_year_id', $year->getKey())
            ->whereBetween('due_on', $this->thisWeek())
            ->tap(fn (Builder $q) => $this->access->scopeToTeacher($q, $user, $year->getKey()))
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{school_class_id: int|string, section_id?: int|string|null, subject_id: int|string, title: string, description: string, assigned_on: string, due_on: string}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(array $data, array $attachments, User $actor): Homework
    {
        $year = $this->context->academicYear()
            ?? throw ValidationException::withMessages(['school_class_id' => 'Set the current academic year in School setup before adding homework.']);

        $attributes = $this->validatedTarget($data, $year, $actor);
        $this->assertAttachmentCount(0, count($attachments));

        return $this->withFileCleanup(function (array &$stored) use ($attributes, $year, $attachments, $actor) {
            $homework = Homework::create($attributes + [
                'academic_year_id' => $year->getKey(),
                'status' => HomeworkStatus::Active,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $this->storeAttachments($homework, 'homework', $attachments, $actor, $stored);

            $this->audit->record('homework.created', $homework, new: $this->auditView($homework) + ['attachments' => count($attachments)], actor: $actor);

            return $homework;
        });
    }

    /**
     * @param  array{school_class_id: int|string, section_id?: int|string|null, subject_id: int|string, title: string, description: string, assigned_on: string, due_on: string}  $data
     * @param  list<UploadedFile>  $attachments
     * @param  list<int|string>  $removeAttachmentIds
     */
    public function update(Homework $homework, array $data, array $attachments, array $removeAttachmentIds, User $actor): Homework
    {
        if ($homework->isArchived()) {
            throw ValidationException::withMessages(['title' => 'Archived homework cannot be edited. Restore it first.']);
        }

        $year = AcademicYear::query()->findOrFail($homework->academic_year_id);
        $attributes = $this->validatedTarget($data, $year, $actor);

        $removeIds = array_map('intval', $removeAttachmentIds);
        $toRemove = $homework->attachments()->whereIn('id', $removeIds)->get();
        $this->assertAttachmentCount($homework->attachments()->count() - $toRemove->count(), count($attachments));

        $updated = $this->withFileCleanup(function (array &$stored) use ($homework, $attributes, $attachments, $toRemove, $actor) {
            /** @var Homework $locked */
            $locked = Homework::query()->lockForUpdate()->findOrFail($homework->getKey());
            $before = $this->auditView($locked);

            $locked->fill($attributes + ['updated_by' => $actor->getKey()])->save();
            $this->storeAttachments($locked, 'homework', $attachments, $actor, $stored);

            [$old, $new] = $this->audit->diff($before, $this->auditView($locked));
            if ($attachments !== [] || $toRemove->isNotEmpty()) {
                $new['attachments_added'] = count($attachments);
                $new['attachments_removed'] = $toRemove->count();
            }
            $this->audit->record('homework.updated', $locked, $old, $new, actor: $actor);

            return $locked;
        });

        // Bytes are removed only after the database change committed.
        $toRemove->each(fn (StoredFile $file) => $this->files->delete($file, $actor));

        return $updated;
    }

    public function archive(Homework $homework, User $actor): Homework
    {
        return DB::transaction(function () use ($homework, $actor) {
            /** @var Homework $locked */
            $locked = Homework::query()->lockForUpdate()->findOrFail($homework->getKey());
            if ($locked->isArchived()) {
                return $locked;
            }

            $locked->forceFill(['status' => HomeworkStatus::Archived, 'archived_at' => now(), 'archived_by' => $actor->getKey(), 'updated_by' => $actor->getKey()])->save();
            $this->audit->record('homework.archived', $locked, ['status' => 'active'], ['status' => 'archived'], actor: $actor);

            return $locked;
        });
    }

    public function restore(Homework $homework, User $actor): Homework
    {
        return DB::transaction(function () use ($homework, $actor) {
            /** @var Homework $locked */
            $locked = Homework::query()->lockForUpdate()->findOrFail($homework->getKey());
            if (! $locked->isArchived()) {
                return $locked;
            }

            $locked->forceFill(['status' => HomeworkStatus::Active, 'archived_at' => null, 'archived_by' => null, 'updated_by' => $actor->getKey()])->save();
            $this->audit->record('homework.restored', $locked, ['status' => 'archived'], ['status' => 'active'], actor: $actor);

            return $locked;
        });
    }

    /**
     * The attachment, if it really belongs to this homework (otherwise 404, so ids cannot be mixed across records).
     */
    public function attachment(Homework $homework, StoredFile $file): StoredFile
    {
        abort_unless(
            $file->purpose === 'homework'
            && $file->attachable_type === $homework->getMorphClass()
            && (int) $file->attachable_id === (int) $homework->getKey(),
            404,
        );

        return $file;
    }

    /**
     * Options for the list filters: classes, the selected class's sections, and subjects.
     *
     * @return array{classes: array<int, string>, sections: array<int, string>, subjects: array<int, string>}
     */
    public function filterOptions(?int $classId): array
    {
        return [
            'classes' => SchoolClass::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'sections' => $classId ? Section::query()->where('school_class_id', $classId)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all() : [],
            'subjects' => Subject::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * Everything the create/edit form needs.
     *
     * @return array{year: ?AcademicYear, options: list<array<string, mixed>>, subjects: array<int, string>}
     */
    public function formData(User $user, ?Homework $homework = null): array
    {
        $year = $homework !== null
            ? AcademicYear::query()->find($homework->academic_year_id)
            : $this->context->academicYear();

        return [
            'year' => $year,
            'options' => $year ? $this->access->formOptions($user, (int) $year->getKey()) : [],
            'subjects' => Subject::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /** Monday..Sunday of the current school week, as Y-m-d strings. @return array{0: string, 1: string} */
    public function thisWeek(): array
    {
        $today = school_today();

        return [$today->startOfWeek()->toDateString(), $today->endOfWeek()->toDateString()];
    }

    /**
     * Business rules on the target of a homework: section of that class, subject offered in the class, the actor may
     * set homework there, the year is open and the assigned date falls inside it.
     *
     * @param  array<string, mixed>  $data
     * @return array{school_class_id: int, section_id: ?int, subject_id: int, title: string, description: string, assigned_on: string, due_on: string}
     */
    private function validatedTarget(array $data, AcademicYear $year, User $actor): array
    {
        $classId = (int) $data['school_class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $subjectId = (int) $data['subject_id'];

        if ($year->isLocked()) {
            throw ValidationException::withMessages(['school_class_id' => "The academic year {$year->name} is locked; homework can no longer be changed."]);
        }

        if ($sectionId !== null && ! Section::query()->whereKey($sectionId)->where('school_class_id', $classId)->exists()) {
            throw ValidationException::withMessages(['section_id' => 'Choose a section of the selected class.']);
        }

        if (! $this->access->subjectOfferedInClass($classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => 'This subject is not taught in the selected class.']);
        }

        if (! $this->access->allows($actor, (int) $year->getKey(), $classId, $sectionId, $subjectId)) {
            throw ValidationException::withMessages(['section_id' => 'You can only set homework for the classes, sections and subjects you are assigned to teach.']);
        }

        $assigned = (string) $data['assigned_on'];
        if ($assigned < $year->starts_on->toDateString() || $assigned > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages(['assigned_on' => "The assigned date must fall inside the academic year {$year->name}."]);
        }

        return [
            'school_class_id' => $classId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'assigned_on' => $assigned,
            'due_on' => (string) $data['due_on'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditView(Homework $homework): array
    {
        return [
            'title' => $homework->title,
            'school_class_id' => (int) $homework->school_class_id,
            'section_id' => $homework->section_id === null ? null : (int) $homework->section_id,
            'subject_id' => (int) $homework->subject_id,
            'assigned_on' => $homework->assigned_on?->toDateString(),
            'due_on' => $homework->due_on?->toDateString(),
            'description_length' => mb_strlen((string) $homework->description),
        ];
    }
}
