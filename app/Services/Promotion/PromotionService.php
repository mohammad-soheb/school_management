<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionItemState;
use App\Enums\Promotion\PromotionOutcome;
use App\Enums\Promotion\PromotionRunStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;
use App\Models\Promotion\PromotionRun;
use App\Models\Promotion\PromotionRunItem;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Year-end promotion / rollover workflow (master plan Phase 2B, FR-2B02..06; docs/promotion/ROLLOVER_RULES.md).
 *
 *   createDraft()  snapshot the active enrollments of the chosen classes as pending items with default outcomes
 *   updateItems()  per-student overrides (outcome, section, roll no, note) while the run is a draft
 *   bulk()         one outcome for many students ("promote all", "retain selected")
 *   commit()       one transaction: lock the run row, re-validate, write the target year, close the source year
 *   cancel()       abandon a draft
 *
 * A committed run is immutable here; individual students are corrected by PromotionCorrectionService.
 */
class PromotionService
{
    /** Interface published by the fees module (FR-2B06). Optional: used only when it exists and is bound. */
    public const DUES_CONTRACT = 'App\\Contracts\\Fees\\DuesCarryForward';

    /** Keys of PromotionRun::$counts. */
    public const COUNT_KEYS = ['promoted', 'retained', 'graduated', 'transferred', 'withdrawn', 'skipped', 'adopted', 'reverted', 'corrected', 'dues_carried_paise', 'source_locked'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PromotionPlacement $placement,
        private readonly RolloverWriter $writer,
        private readonly AcademicYearLockService $locks,
    ) {}

    /**
     * @param  array{from_academic_year_id: int|string, to_academic_year_id: int|string, class_ids: list<int|string>, carry_forward_dues?: bool, lock_source_year?: bool, idempotency_key: string}  $data
     */
    public function createDraft(array $data, User $actor): PromotionRun
    {
        $existing = PromotionRun::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): PromotionRun => $this->buildDraft($data, $actor));
        } catch (UniqueConstraintViolationException $e) {
            // A double submit raced us between the lookup and the insert: return the run the first request made.
            return PromotionRun::query()->where('idempotency_key', $data['idempotency_key'])->first() ?? throw $e;
        }
    }

    /**
     * Per-student overrides for a draft.
     *
     * @param  array<int|string, array{outcome?: string, target_section_id?: int|string|null, target_roll_no?: string|null, note?: string|null}>  $rows  keyed by item id
     * @return int number of items changed
     */
    public function updateItems(PromotionRun $run, array $rows, User $actor): int
    {
        return DB::transaction(function () use ($run, $rows, $actor): int {
            $run = $this->lockDraft($run);

            $items = $run->items()->with(['fromClass', 'enrollment.section'])->whereIn('id', array_keys($rows))->get()->keyBy('id');
            if ($items->count() !== count($rows)) {
                throw ValidationException::withMessages(['items' => 'Some students do not belong to this run. Reload the page and try again.']);
            }

            $sections = $this->targetSections($items);
            $changed = 0;
            $errors = [];

            foreach ($rows as $id => $row) {
                /** @var PromotionRunItem $item */
                $item = $items[(int) $id];
                if (! $item->isPending()) {
                    continue;
                }

                $outcome = isset($row['outcome']) ? PromotionOutcome::from($row['outcome']) : $item->outcome;
                $targetClassId = $this->placement->targetClassId($outcome, $item->fromClass);

                if ($outcome === PromotionOutcome::Promote && $targetClassId === null) {
                    $errors["items.{$id}.outcome"] = "{$item->fromClass->name} has no next class. Choose Graduate, or set the next class in school setup.";

                    continue;
                }

                $item->fill([
                    'outcome' => $outcome,
                    'target_class_id' => $targetClassId,
                    'target_section_id' => $this->resolveSection($sections, $targetClassId, $row['target_section_id'] ?? $item->target_section_id, $item),
                    'target_roll_no' => $targetClassId === null ? null : $this->blankToNull($row['target_roll_no'] ?? $item->target_roll_no),
                    'note' => array_key_exists('note', $row) ? $this->blankToNull($row['note']) : $item->note,
                ]);

                if ($item->isDirty()) {
                    $item->save();
                    $changed++;
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($changed > 0) {
                $this->audit->record('promotion.items_updated', $run, [], ['items' => $changed], actor: $actor);
            }

            return $changed;
        });
    }

    /**
     * Apply one outcome (or "default") to the given pending items.
     *
     * @param  list<int|string>  $itemIds
     * @return int number of items changed
     */
    public function bulk(PromotionRun $run, string $action, array $itemIds, User $actor): int
    {
        return DB::transaction(function () use ($run, $action, $itemIds, $actor): int {
            $run = $this->lockDraft($run);

            $items = $run->items()->with(['fromClass', 'enrollment.section'])
                ->whereIn('id', $itemIds)
                ->where('state', PromotionItemState::Pending)
                ->get();

            $sections = $this->targetSections($items);
            $changed = 0;
            $blocked = [];

            foreach ($items as $item) {
                $outcome = $action === 'default'
                    ? $this->placement->defaultOutcome($item->fromClass)
                    : PromotionOutcome::from($action);
                $targetClassId = $this->placement->targetClassId($outcome, $item->fromClass);

                if ($outcome === PromotionOutcome::Promote && $targetClassId === null) {
                    $blocked[$item->fromClass->name] = true;

                    continue;
                }

                $keepsClass = $item->target_class_id !== null && $targetClassId === (int) $item->target_class_id;
                $item->fill([
                    'outcome' => $outcome,
                    'target_class_id' => $targetClassId,
                    'target_section_id' => $this->resolveSection($sections, $targetClassId, $keepsClass ? $item->target_section_id : null, $item),
                    'target_roll_no' => $targetClassId === null ? null : ($item->target_roll_no ?? $item->enrollment->roll_no),
                ]);

                if ($item->isDirty()) {
                    $item->save();
                    $changed++;
                }
            }

            if ($blocked !== []) {
                throw ValidationException::withMessages([
                    'bulk' => implode(', ', array_keys($blocked)).' has no next class, so its students cannot be promoted. Choose Graduate, or set the next class in school setup.',
                ]);
            }

            if ($changed > 0) {
                $this->audit->record('promotion.items_updated', $run, [], ['items' => $changed, 'bulk' => $action], actor: $actor);
            }

            return $changed;
        });
    }

    public function cancel(PromotionRun $run, User $actor): PromotionRun
    {
        return DB::transaction(function () use ($run, $actor): PromotionRun {
            $run = $this->lockDraft($run);

            $run->forceFill([
                'status' => PromotionRunStatus::Cancelled,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
            ])->save();

            $this->audit->record('promotion.cancelled', $run, ['status' => 'draft'], ['status' => 'cancelled'], actor: $actor);

            return $run;
        });
    }

    /**
     * Commit the run (FR-2B05). Idempotent: committing a committed run returns its stored result and writes nothing.
     * Concurrency: the run row is locked first, so a second commit waits, then sees "committed"; the source
     * enrollments are locked too, and uq_enrollment_student_year forbids a second enrollment in the target year.
     */
    public function commit(PromotionRun $run, User $actor): PromotionResult
    {
        return DB::transaction(function () use ($run, $actor): PromotionResult {
            /** @var PromotionRun $run */
            $run = PromotionRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();

            if ($run->isCommitted()) {
                return new PromotionResult($run, $run->counts ?? [], alreadyCommitted: true);
            }

            if (! $run->isDraft()) {
                throw ValidationException::withMessages(['run' => 'This run was cancelled and cannot be committed. Start a new run.']);
            }

            $from = AcademicYear::query()->whereKey($run->from_academic_year_id)->lockForUpdate()->firstOrFail();
            $to = AcademicYear::query()->whereKey($run->to_academic_year_id)->lockForUpdate()->firstOrFail();
            $this->assertYearsWritable($from, $to);

            $items = $run->items()->with('fromClass')->where('state', PromotionItemState::Pending)->orderBy('id')->get();
            $this->assertCommittable($items);

            /** @var Collection<int, StudentEnrollment> $enrollments */
            $enrollments = StudentEnrollment::query()->whereIn('id', $items->pluck('student_enrollment_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var Collection<int, Student> $students */
            $students = Student::query()->whereIn('id', $items->pluck('student_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $sections = $this->targetSections($items);
            $carry = $this->duesCarrier($run);
            $today = school_today();
            $counts = array_fill_keys(self::COUNT_KEYS, 0);

            foreach ($items as $item) {
                $source = $enrollments[$item->student_enrollment_id];
                $student = $students[$item->student_id];

                if ($source->status !== EnrollmentStatus::Active || (int) $source->academic_year_id !== (int) $from->getKey()) {
                    $this->skip($item, 'source_changed');

                    continue;
                }

                if ($student->status !== StudentStatus::Active) {
                    $this->skip($item, 'student_inactive');

                    continue;
                }

                $outcome = $item->outcome;
                $target = null;
                $leftOn = $this->placement->leftOn($outcome, $from, $source, $today);

                if ($outcome->createsEnrollment()) {
                    $sectionId = $this->resolveSection($sections, $item->target_class_id, $item->target_section_id, $item, $source);
                    [$target, $placed] = $this->writer->place($source, $run, $to, (int) $item->target_class_id, $sectionId, $item->target_roll_no);

                    if ($placed === RolloverWriter::PLACED_CONFLICT) {
                        $this->skip($item, 'already_enrolled');

                        continue;
                    }

                    if ($placed === RolloverWriter::PLACED_ADOPTED) {
                        $counts['adopted']++;
                    }

                    $item->target_section_id = $sectionId;
                } else {
                    $hasTarget = StudentEnrollment::query()->where('student_id', $student->getKey())->where('academic_year_id', $to->getKey())->exists();
                    if ($hasTarget) {
                        $this->skip($item, 'already_enrolled');

                        continue;
                    }

                    $item->previous_student_status = $student->status->value;
                    $this->writer->changeStudentStatus(
                        $student,
                        $outcome->studentStatus(),
                        $source,
                        $leftOn,
                        "Year-end promotion run #{$run->getKey()}: {$outcome->label()}".($item->note ? " ({$item->note})" : ''),
                        $actor,
                    );
                }

                // FR-2B04: the source enrollment keeps its class, section, roll no and everything recorded against it;
                // only its status and left_on change.
                $source->fill(['status' => $outcome->enrollmentStatus(), 'left_on' => $leftOn])->save();

                if ($target !== null && $carry !== null) {
                    $item->dues_carried_paise = max(0, (int) $carry->carryForward($source, $target, $actor));
                    $counts['dues_carried_paise'] += $item->dues_carried_paise;
                }

                $item->fill([
                    'state' => PromotionItemState::Applied,
                    'target_enrollment_id' => $target?->getKey(),
                    'applied_at' => now(),
                ])->save();

                $counts[$outcome->countKey()]++;
            }

            $counts['skipped'] = $run->items()->where('state', PromotionItemState::Skipped)->count();

            $run->forceFill([
                'status' => PromotionRunStatus::Committed,
                'committed_by' => $actor->getKey(),
                'committed_at' => now(),
                'counts' => $counts,
            ])->save();

            if ($run->option('lock_source_year', false)) {
                $counts['source_locked'] = $this->locks->lockSourceYear($run, $actor) ? 1 : 0;
                $run->forceFill(['counts' => $counts])->save();
            }

            $this->audit->record(
                'promotion.committed',
                $run,
                ['status' => 'draft'],
                ['status' => 'committed', 'counts' => $counts],
                ['from_year' => $from->name, 'to_year' => $to->name],
                $actor,
            );

            return new PromotionResult($run, $counts);
        });
    }

    /**
     * L1 on demand: lock the source year of a committed run (typically after the new year was made current).
     * Returns false when the year must stay open because it is still the current year.
     */
    public function lockSourceYear(PromotionRun $run, User $actor): bool
    {
        if (! $run->isCommitted()) {
            throw ValidationException::withMessages(['run' => 'Commit the run before locking its source year.']);
        }

        return DB::transaction(function () use ($run, $actor): bool {
            $locked = $this->locks->lockSourceYear($run, $actor);

            if ($locked) {
                $run = PromotionRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();
                $run->forceFill(['counts' => ['source_locked' => 1] + ($run->counts ?? [])])->save();
            }

            return $locked;
        });
    }

    /** @param  array<string, mixed>  $data */
    private function buildDraft(array $data, User $actor): PromotionRun
    {
        /** @var AcademicYear $from */
        $from = AcademicYear::query()->findOrFail($data['from_academic_year_id']);
        /** @var AcademicYear $to */
        $to = AcademicYear::query()->findOrFail($data['to_academic_year_id']);

        if ($to->starts_on->lte($from->starts_on)) {
            throw ValidationException::withMessages(['to_academic_year_id' => "The target year must start after {$from->name}."]);
        }

        $this->assertYearsWritable($from, $to, sourceKey: 'from_academic_year_id', targetKey: 'to_academic_year_id');

        $classIds = array_values(array_unique(array_map('intval', $data['class_ids'])));
        $this->assertNoOverlappingDraft($from, $classIds);

        $enrollments = StudentEnrollment::query()
            ->with(['student', 'section', 'schoolClass'])
            ->where('academic_year_id', $from->getKey())
            ->whereIn('school_class_id', $classIds)
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('school_class_id')
            ->orderBy('section_id')
            ->orderByRaw('CAST(roll_no AS UNSIGNED), roll_no')
            ->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages(['class_ids' => "No students are actively enrolled in the selected classes in {$from->name}."]);
        }

        $classes = SchoolClass::query()->whereIn('id', $classIds)->get()->keyBy('id');
        $sections = $this->placement->sectionsFor($classes->flatMap(fn (SchoolClass $c) => [$c->getKey(), $c->next_class_id]));

        $run = PromotionRun::create([
            'from_academic_year_id' => $from->getKey(),
            'to_academic_year_id' => $to->getKey(),
            'status' => PromotionRunStatus::Draft,
            'options' => [
                'class_ids' => $classIds,
                'carry_forward_dues' => (bool) ($data['carry_forward_dues'] ?? true),
                'lock_source_year' => (bool) ($data['lock_source_year'] ?? false),
            ],
            'idempotency_key' => $data['idempotency_key'],
            'created_by' => $actor->getKey(),
        ]);

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $class = $classes[$enrollment->school_class_id];
            $outcome = $this->placement->defaultOutcome($class);
            $targetClassId = $this->placement->targetClassId($outcome, $class);
            $active = $enrollment->student->status === StudentStatus::Active;

            $rows[] = [
                'student_enrollment_id' => $enrollment->getKey(),
                'student_id' => $enrollment->student_id,
                'from_class_id' => $enrollment->school_class_id,
                'outcome' => $outcome,
                'target_class_id' => $targetClassId,
                'target_section_id' => $targetClassId === null ? null : $this->placement->defaultSectionId(
                    $sections->get($targetClassId, collect()),
                    $enrollment->section,
                    $targetClassId === (int) $enrollment->school_class_id,
                ),
                'target_roll_no' => $targetClassId === null ? null : $enrollment->roll_no,
                'state' => $active ? PromotionItemState::Pending : PromotionItemState::Skipped,
                'skip_reason' => $active ? null : 'student_inactive',
            ];
        }

        $run->items()->createMany($rows);

        $this->audit->record(
            'promotion.drafted',
            $run,
            [],
            ['from_year' => $from->name, 'to_year' => $to->name, 'classes' => count($classIds), 'students' => count($rows)],
            actor: $actor,
        );

        return $run;
    }

    /** Lock the run row and require it to still be a draft. */
    private function lockDraft(PromotionRun $run): PromotionRun
    {
        /** @var PromotionRun $locked */
        $locked = PromotionRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isDraft()) {
            throw ValidationException::withMessages(['run' => "This run is {$locked->status->label()} and can no longer be changed."]);
        }

        return $locked;
    }

    private function assertYearsWritable(AcademicYear $from, AcademicYear $to, string $sourceKey = 'run', string $targetKey = 'run'): void
    {
        if ($to->isLocked()) {
            throw ValidationException::withMessages([$targetKey => "{$to->name} is locked. Reopen it before promoting students into it."]);
        }

        if ($from->isLocked()) {
            throw ValidationException::withMessages([$sourceKey => "{$from->name} is locked. Reopen it before closing its enrollments."]);
        }
    }

    /** @param  list<int>  $classIds */
    private function assertNoOverlappingDraft(AcademicYear $from, array $classIds): void
    {
        $drafts = PromotionRun::query()
            ->where('from_academic_year_id', $from->getKey())
            ->where('status', PromotionRunStatus::Draft)
            ->get();

        foreach ($drafts as $draft) {
            if (array_intersect($draft->classIds(), $classIds) !== []) {
                throw ValidationException::withMessages([
                    'class_ids' => "Draft run #{$draft->getKey()} already covers some of these classes. Continue it, or cancel it first.",
                ]);
            }
        }
    }

    /** @param  Collection<int, PromotionRunItem>  $items */
    private function assertCommittable(Collection $items): void
    {
        $missing = $items
            ->filter(fn (PromotionRunItem $i) => $i->outcome->createsEnrollment() && $i->target_class_id === null)
            ->map(fn (PromotionRunItem $i) => $i->fromClass->name)
            ->unique();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'run' => 'Some students have no target class ('.$missing->implode(', ').'). Choose Graduate for them or set the next class in school setup.',
            ]);
        }
    }

    private function skip(PromotionRunItem $item, string $reason): void
    {
        $item->fill(['state' => PromotionItemState::Skipped, 'skip_reason' => $reason])->save();
    }

    /**
     * Sections of every class an item could be placed in, grouped by class id.
     *
     * @param  Collection<int, PromotionRunItem>  $items
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Section>>
     */
    private function targetSections(Collection $items): \Illuminate\Support\Collection
    {
        return $this->placement->sectionsFor(
            $items->flatMap(fn (PromotionRunItem $i) => [$i->target_class_id, $i->from_class_id, $i->fromClass?->next_class_id]),
        );
    }

    /**
     * P2/P4: keep the requested section when it belongs to the target class, otherwise fall back to the default one.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Section>>  $sections
     */
    private function resolveSection(\Illuminate\Support\Collection $sections, ?int $targetClassId, int|string|null $requested, PromotionRunItem $item, ?StudentEnrollment $source = null): ?int
    {
        if ($targetClassId === null) {
            return null;
        }

        $classSections = $sections->get($targetClassId, collect());

        if ($requested !== null && $requested !== '' && $classSections->contains('id', (int) $requested)) {
            return (int) $requested;
        }

        $source ??= $item->relationLoaded('enrollment') ? $item->enrollment : null;
        $current = $source?->relationLoaded('section') ? $source->section : ($source?->section_id ? Section::query()->find($source->section_id) : null);

        return $this->placement->defaultSectionId($classSections, $current, $targetClassId === (int) $item->from_class_id);
    }

    private function duesCarrier(PromotionRun $run): ?object
    {
        if (! $run->option('carry_forward_dues', true)) {
            return null;
        }

        if (! interface_exists(self::DUES_CONTRACT) || ! app()->bound(self::DUES_CONTRACT)) {
            return null;
        }

        return app(self::DUES_CONTRACT);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
