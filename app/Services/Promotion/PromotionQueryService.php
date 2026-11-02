<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionItemState;
use App\Enums\Promotion\PromotionOutcome;
use App\Enums\Promotion\PromotionRunStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Models\Promotion\PromotionRun;
use App\Models\Promotion\PromotionRunItem;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\StudentEnrollment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read side of the rollover: run list, wizard options and the preview (no writes, FR-2B02).
 */
class PromotionQueryService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PromotionPlacement $placement,
    ) {}

    /** @return LengthAwarePaginator<int, PromotionRun> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PromotionRun::query()
            ->with(['fromYear:id,name', 'toYear:id,name', 'creator:id,name', 'committer:id,name'])
            ->withCount('items')
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return Collection<int, AcademicYear> newest first */
    public function years(): Collection
    {
        return AcademicYear::query()->with('lockedBy:id,name')->orderByDesc('starts_on')->get();
    }

    /**
     * Data for the "new run" wizard: source year (default: the current year), suggested target year and the classes of
     * the source year with their number of active students.
     *
     * @return array{years: Collection<int, AcademicYear>, from: AcademicYear|null, targets: Collection<int, AcademicYear>, suggestedTo: AcademicYear|null, classes: Collection<int, array{class: SchoolClass, students: int, next: string|null, inDraft: int|null}>}
     */
    public function createOptions(?int $fromId): array
    {
        $years = AcademicYear::query()->orderBy('starts_on')->get();
        $from = ($fromId !== null ? $years->firstWhere('id', $fromId) : null)
            ?? $years->firstWhere('is_current', true)
            ?? $years->last();

        $targets = $from === null ? collect() : $years->filter(fn (AcademicYear $y) => $y->starts_on->gt($from->starts_on))->values();
        $suggestedTo = $targets->first(fn (AcademicYear $y) => ! $y->isLocked());

        $classes = collect();
        if ($from !== null) {
            $counts = StudentEnrollment::query()
                ->where('academic_year_id', $from->getKey())
                ->where('status', EnrollmentStatus::Active)
                ->selectRaw('school_class_id, COUNT(*) AS students')
                ->groupBy('school_class_id')
                ->pluck('students', 'school_class_id');

            $drafts = [];
            PromotionRun::query()->where('from_academic_year_id', $from->getKey())->where('status', PromotionRunStatus::Draft)->get()
                ->each(function (PromotionRun $run) use (&$drafts): void {
                    foreach ($run->classIds() as $classId) {
                        $drafts[$classId] = $run->getKey();
                    }
                });

            $classes = SchoolClass::query()->with('nextClass:id,name')
                ->whereIn('id', $counts->keys())
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $class) => [
                    'class' => $class,
                    'students' => (int) $counts[$class->getKey()],
                    'next' => $class->nextClass?->name,
                    'inDraft' => $drafts[$class->getKey()] ?? null,
                ]);
        }

        return ['years' => $years->sortByDesc('starts_on')->values(), 'from' => $from, 'targets' => $targets, 'suggestedTo' => $suggestedTo, 'classes' => $classes];
    }

    /**
     * Everything the run page needs except the item rows: per-class tabs, outcome totals and warnings.
     *
     * @return array{classes: Collection<int, array{class: SchoolClass, total: int}>, totals: array<string, int>, warnings: list<array{tone: string, title: string, message: string}>}
     */
    public function overview(PromotionRun $run): array
    {
        $run->loadMissing(['fromYear', 'toYear']);

        $perClass = $run->items()->selectRaw('from_class_id, COUNT(*) AS total')->groupBy('from_class_id')->pluck('total', 'from_class_id');
        $classes = SchoolClass::query()->whereIn('id', $perClass->keys())->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (SchoolClass $class) => ['class' => $class, 'total' => (int) $perClass[$class->getKey()]]);

        $totals = array_fill_keys(array_map(fn (PromotionOutcome $o) => $o->value, PromotionOutcome::cases()), 0)
            + ['pending' => 0, 'applied' => 0, 'skipped' => 0, 'reverted' => 0];

        $run->items()->selectRaw('outcome, state, COUNT(*) AS n')->groupBy('outcome', 'state')->get()
            ->each(function ($row) use (&$totals): void {
                $state = $row->state instanceof PromotionItemState ? $row->state->value : (string) $row->state;
                $outcome = $row->outcome instanceof PromotionOutcome ? $row->outcome->value : (string) $row->outcome;
                $totals[$state] = ($totals[$state] ?? 0) + (int) $row->n;
                if (in_array($state, ['pending', 'applied'], true)) {
                    $totals[$outcome] += (int) $row->n;
                }
            });

        return ['classes' => $classes, 'totals' => $totals, 'warnings' => $run->isDraft() ? $this->warnings($run) : []];
    }

    /**
     * Item rows of one class, with what each select needs.
     *
     * @return array{items: Collection<int, PromotionRunItem>, sectionOptions: array<int, array<string, array<int, string>>>, flags: array<int, list<string>>}
     */
    public function classItems(PromotionRun $run, int $classId): array
    {
        $items = $run->items()
            ->with([
                'student:id,admission_no,first_name,last_name,status',
                'enrollment:id,section_id,roll_no,status',
                'enrollment.section:id,name',
                'fromClass:id,name,next_class_id',
                'targetClass:id,name',
                'targetSection:id,name',
                'targetEnrollment:id,school_class_id,section_id,roll_no,status,promotion_run_id',
            ])
            ->where('from_class_id', $classId)
            ->get()
            ->sortBy(fn (PromotionRunItem $i) => sprintf('%s|%010d|%s', $i->enrollment->section?->name, (int) $i->enrollment->roll_no, $i->student->first_name))
            ->values();

        $class = $items->first()?->fromClass;
        $sectionOptions = [];
        if ($class !== null) {
            $candidates = array_filter([$class->next_class_id, $class->getKey()]);
            $sections = $this->placement->sectionsFor($candidates);
            $names = SchoolClass::query()->whereIn('id', $candidates)->pluck('name', 'id');
            foreach ($candidates as $candidateId) {
                $label = (int) $candidateId === (int) $class->getKey() ? "{$names[$candidateId]} (retain)" : "{$names[$candidateId]} (promote)";
                $sectionOptions[$label] = $sections->get($candidateId, collect())->pluck('name', 'id')->all();
            }
        }

        $flags = [];
        if ($run->isDraft() && $items->isNotEmpty()) {
            $enrolled = StudentEnrollment::query()
                ->where('academic_year_id', $run->to_academic_year_id)
                ->whereIn('student_id', $items->pluck('student_id'))
                ->pluck('status', 'student_id');

            foreach ($items as $item) {
                if (isset($enrolled[$item->student_id])) {
                    $flags[$item->getKey()][] = $item->outcome->createsEnrollment()
                        ? 'Already enrolled in the target year: will be linked, not enrolled twice.'
                        : 'Already enrolled in the target year: will be skipped.';
                }
                if ($item->outcome->createsEnrollment() && $item->target_section_id === null) {
                    $flags[$item->getKey()][] = 'No section in the target class.';
                }
            }
        }

        return ['items' => $items, 'sectionOptions' => $sectionOptions, 'flags' => $flags];
    }

    /**
     * Problems to show before commit (FR-2B03). None of them blocks the commit except where noted in the message.
     *
     * @return list<array{tone: string, title: string, message: string}>
     */
    public function warnings(PromotionRun $run): array
    {
        $run->loadMissing(['fromYear', 'toYear']);
        $warnings = [];

        foreach (['fromYear' => 'source', 'toYear' => 'target'] as $relation => $label) {
            if ($run->{$relation}->isLocked()) {
                $warnings[] = ['tone' => 'danger', 'title' => "The {$label} year is locked", 'message' => "{$run->{$relation}->name} must be reopened before this run can be committed."];
            }
        }

        $pending = $run->items()->where('state', PromotionItemState::Pending)->get(['id', 'student_id', 'outcome', 'from_class_id', 'target_class_id', 'target_section_id']);
        $continuing = $pending->filter(fn (PromotionRunItem $i) => $i->outcome->createsEnrollment());

        // Promote with no next class (blocks the commit).
        $noTarget = $continuing->whereNull('target_class_id')->count();
        if ($noTarget > 0) {
            $warnings[] = ['tone' => 'danger', 'title' => 'Students without a target class', 'message' => "{$noTarget} student(s) are set to Promote from a class with no next class. Choose Graduate or set the next class in school setup."];
        }

        // Target classes without sections.
        $targetClassIds = $continuing->pluck('target_class_id')->filter()->unique();
        $sections = Section::query()->whereIn('school_class_id', $targetClassIds)->get(['id', 'school_class_id', 'name', 'capacity']);
        $withoutSections = $targetClassIds->diff($sections->pluck('school_class_id')->unique());
        if ($withoutSections->isNotEmpty()) {
            $names = SchoolClass::query()->whereIn('id', $withoutSections)->orderBy('sort_order')->pluck('name')->implode(', ');
            $warnings[] = ['tone' => 'warning', 'title' => 'Target classes without sections', 'message' => "{$names} has no sections. Students will be enrolled without a section unless you add sections in school setup first."];
        }

        // Already enrolled in the target year.
        $already = StudentEnrollment::query()->where('academic_year_id', $run->to_academic_year_id)->whereIn('student_id', $pending->pluck('student_id'))->count();
        if ($already > 0) {
            $warnings[] = ['tone' => 'info', 'title' => 'Students already enrolled in the target year', 'message' => "{$already} student(s) already have an enrollment in {$run->toYear->name}. Promote/retain links them to it; other outcomes skip them. Nobody is enrolled twice."];
        }

        // Section capacity: students already in the section in the target year + new placements from this run.
        $existing = StudentEnrollment::query()
            ->where('academic_year_id', $run->to_academic_year_id)
            ->where('status', EnrollmentStatus::Active)
            ->whereIn('section_id', $sections->pluck('id'))
            ->selectRaw('section_id, COUNT(*) AS n')->groupBy('section_id')
            ->pluck('n', 'section_id');
        $alreadyStudents = StudentEnrollment::query()->where('academic_year_id', $run->to_academic_year_id)->whereIn('student_id', $pending->pluck('student_id'))->pluck('student_id')->flip();
        $planned = $continuing->reject(fn (PromotionRunItem $i) => isset($alreadyStudents[$i->student_id]))->countBy('target_section_id');
        $classNames = SchoolClass::query()->whereIn('id', $targetClassIds)->pluck('name', 'id');

        foreach ($sections as $section) {
            $total = (int) ($existing[$section->getKey()] ?? 0) + (int) ($planned[$section->getKey()] ?? 0);
            if ($section->capacity !== null && $section->capacity > 0 && $total > $section->capacity) {
                $warnings[] = ['tone' => 'warning', 'title' => 'Section over capacity', 'message' => "{$classNames[$section->school_class_id]} - {$section->name} would have {$total} students (capacity {$section->capacity})."];
            }
        }

        // Skipped because the student is not active.
        $inactive = $run->items()->where('state', PromotionItemState::Skipped)->where('skip_reason', 'student_inactive')->count();
        if ($inactive > 0) {
            $warnings[] = ['tone' => 'info', 'title' => 'Inactive students skipped', 'message' => "{$inactive} student(s) are not active and will be left untouched."];
        }

        // Students who joined the chosen classes after the draft was prepared are not part of it.
        $missing = StudentEnrollment::query()
            ->where('academic_year_id', $run->from_academic_year_id)
            ->whereIn('school_class_id', $run->classIds())
            ->where('status', EnrollmentStatus::Active)
            ->whereNotIn('id', $run->items()->select('student_enrollment_id'))
            ->count();
        if ($missing > 0) {
            $warnings[] = ['tone' => 'warning', 'title' => 'New students not in this draft', 'message' => "{$missing} student(s) joined these classes after the draft was prepared. They will not be promoted by this run; cancel it and start a new one to include them."];
        }

        return $warnings;
    }
}
