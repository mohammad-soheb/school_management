<?php

namespace App\Services\Academics;

use App\Enums\Academics\ExamStatus;
use App\Enums\Academics\MarkStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Models\Academics\Exam;
use App\Models\Academics\ExamClass;
use App\Models\Academics\ExamComponent;
use App\Models\Academics\ExamSubject;
use App\Models\Academics\Mark;
use App\Models\Academics\MarkSheet;
use App\Models\Academics\ResultPublication;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exam set-up and workflow (FR-501, FR-502): create an exam for an academic year and a set of classes, configure
 * the examined subjects per class (max / pass marks, optional components), and move it through
 * draft -> marks_open -> (published by ResultPublicationService) -> locked.
 */
class ExamService
{
    public function __construct(
        private readonly GradingSchemeService $schemes,
        private readonly ExamSettings $settings,
        private readonly ExamAccess $access,
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    /** @param  array{year_id?: mixed, status?: mixed, q?: mixed}  $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $yearId = $filters['year_id'] ?? null;
        $yearId = $yearId === null || $yearId === '' ? $this->context->academicYear()?->getKey() : (int) $yearId;
        $status = ExamStatus::tryFrom((string) ($filters['status'] ?? ''));
        $q = trim((string) ($filters['q'] ?? ''));

        return Exam::query()
            ->with(['academicYear', 'classes'])
            ->withCount('examClasses')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('term', 'like', "%{$q}%")))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /** @return array<int, string> */
    public function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('starts_on')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function classOptions(): array
    {
        return SchoolClass::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @param  array{academic_year_id: int, grading_scheme_id?: ?int, name: string, term?: ?string, starts_on: string, ends_on: string, description?: ?string, class_ids: list<int>}  $data
     */
    public function create(array $data, User $actor): Exam
    {
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        if ($year->isLocked()) {
            throw ValidationException::withMessages(['academic_year_id' => 'This academic year is locked.']);
        }

        $schemeId = ! empty($data['grading_scheme_id'])
            ? (int) $data['grading_scheme_id']
            : $this->schemes->ensureDefault($actor)->getKey();

        return DB::transaction(function () use ($data, $actor, $schemeId) {
            $exam = Exam::create([
                'academic_year_id' => $data['academic_year_id'],
                'grading_scheme_id' => $schemeId,
                'name' => $data['name'],
                'term' => $data['term'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'description' => $data['description'] ?? null,
                'status' => ExamStatus::Draft,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            foreach (array_unique(array_map('intval', $data['class_ids'])) as $classId) {
                $this->addClass($exam, $classId);
            }

            $this->audit->record('exam.created', $exam, new: [
                'name' => $exam->name, 'term' => $exam->term, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'classes' => array_values(array_map('intval', $data['class_ids'])),
            ], actor: $actor);

            return $exam;
        });
    }

    /**
     * Name, term, dates and description can change until the exam is locked; the grading scheme and the classes
     * only while it is a draft.
     *
     * @param  array{grading_scheme_id?: ?int, name: string, term?: ?string, starts_on: string, ends_on: string, description?: ?string, class_ids?: list<int>}  $data
     */
    public function update(Exam $exam, array $data, User $actor): Exam
    {
        if ($exam->isLocked()) {
            throw ValidationException::withMessages(['name' => 'A locked exam cannot be changed.']);
        }

        return DB::transaction(function () use ($exam, $data, $actor) {
            $before = $this->auditable($exam);

            $exam->fill([
                'name' => $data['name'],
                'term' => $data['term'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'description' => $data['description'] ?? null,
                'updated_by' => $actor->getKey(),
            ]);

            $setupChanging = (isset($data['grading_scheme_id']) && (int) $data['grading_scheme_id'] !== (int) $exam->grading_scheme_id)
                || (isset($data['class_ids']) && $this->classIds($exam) !== $this->sortedIds($data['class_ids']));

            if ($setupChanging && ! $exam->isDraft()) {
                throw ValidationException::withMessages(['class_ids' => 'Classes and grading scheme can only change while the exam is a draft. Re-open set-up first.']);
            }

            if (isset($data['grading_scheme_id'])) {
                $exam->grading_scheme_id = (int) $data['grading_scheme_id'];
            }
            $exam->save();

            if (isset($data['class_ids']) && $exam->isDraft()) {
                $this->syncClasses($exam, $this->sortedIds($data['class_ids']));
            }

            [$old, $new] = $this->audit->diff($before, $this->auditable($exam->refresh()));
            if ($new !== []) {
                $this->audit->record('exam.updated', $exam, $old, $new, actor: $actor);
            }

            return $exam;
        });
    }

    /**
     * Rows for the "subjects for a class" form: every subject taught in the class plus any already examined.
     *
     * @return list<array{subject_id: int, name: string, code: ?string, included: bool, max_marks: string, pass_marks: string, counts_in_total: bool, has_marks: bool, components: list<array{name: string, max_marks: string, pass_marks: ?string}>}>
     */
    public function subjectRows(Exam $exam, SchoolClass $class): array
    {
        $this->assertClassInExam($exam, (int) $class->getKey());

        $existing = ExamSubject::query()->with(['subject', 'components'])
            ->where('exam_id', $exam->getKey())->where('school_class_id', $class->getKey())
            ->get()->keyBy('subject_id');
        $withMarks = $this->subjectsWithMarks($existing->pluck('id')->all());

        $offered = ClassSubject::query()->with('subject')->where('school_class_id', $class->getKey())->orderBy('sort_order')->get();

        $rows = [];
        foreach ($offered as $classSubject) {
            $rows[$classSubject->subject_id] = $this->subjectRow($classSubject->subject_id, $classSubject->subject->name, $classSubject->subject->code, $existing->get($classSubject->subject_id), $withMarks);
        }
        foreach ($existing as $subjectId => $examSubject) {
            $rows[$subjectId] ??= $this->subjectRow($subjectId, $examSubject->subject->name, $examSubject->subject->code, $examSubject, $withMarks);
        }

        return array_values($rows);
    }

    /**
     * @param  array<int|string, array{include?: mixed, max_marks?: mixed, pass_marks?: mixed, counts_in_total?: mixed, components?: list<array{name?: ?string, max_marks?: mixed, pass_marks?: mixed}>}>  $subjects  keyed by subject id
     */
    public function updateClassSubjects(Exam $exam, SchoolClass $class, array $subjects, User $actor): void
    {
        $this->assertClassInExam($exam, (int) $class->getKey());

        if (! $exam->isDraft()) {
            throw ValidationException::withMessages(['subjects' => 'Subjects can only change while the exam is a draft. Re-open set-up first.']);
        }

        DB::transaction(function () use ($exam, $class, $subjects, $actor) {
            $existing = ExamSubject::query()->with(['subject', 'components'])
                ->where('exam_id', $exam->getKey())->where('school_class_id', $class->getKey())
                ->get()->keyBy('subject_id');
            $offered = ClassSubject::query()->where('school_class_id', $class->getKey())->pluck('sort_order', 'subject_id');
            $withMarks = $this->subjectsWithMarks($existing->pluck('id')->all());

            $errors = [];
            $changes = [];

            foreach ($subjects as $subjectId => $input) {
                $subjectId = (int) $subjectId;
                $current = $existing->get($subjectId);

                if ($current === null && ! $offered->has($subjectId)) {
                    $errors["subjects.{$subjectId}.include"] = 'This subject is not taught in '.$class->name.'.';

                    continue;
                }

                $include = filter_var($input['include'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $name = $current?->subject->name ?? 'Subject';

                if (! $include) {
                    if ($current === null) {
                        continue;
                    }
                    if (in_array($current->getKey(), $withMarks['any'], true)) {
                        $errors["subjects.{$subjectId}.include"] = "{$name} already has marks recorded, so it cannot be removed from the exam.";

                        continue;
                    }
                    ExamComponent::query()->where('exam_subject_id', $current->getKey())->delete();
                    MarkSheet::query()->where('exam_subject_id', $current->getKey())->delete();
                    $current->delete();
                    $changes[$subjectId] = 'removed';

                    continue;
                }

                $components = collect($input['components'] ?? [])
                    ->filter(fn ($c) => filled($c['name'] ?? null))
                    ->map(fn ($c) => ['name' => trim((string) $c['name']), 'max_marks' => (string) ($c['max_marks'] ?? ''), 'pass_marks' => filled($c['pass_marks'] ?? null) ? (string) $c['pass_marks'] : null])
                    ->values();

                foreach ($components as $i => $component) {
                    if (! is_numeric($component['max_marks']) || (float) $component['max_marks'] <= 0) {
                        $errors["subjects.{$subjectId}.components.{$i}.max_marks"] = "{$component['name']}: enter the maximum marks.";
                    } elseif ($component['pass_marks'] !== null && (float) $component['pass_marks'] > (float) $component['max_marks']) {
                        $errors["subjects.{$subjectId}.components.{$i}.pass_marks"] = "{$component['name']}: pass marks cannot exceed the maximum.";
                    }
                }
                if ($components->pluck('name')->map(fn ($n) => mb_strtolower($n))->duplicates()->isNotEmpty()) {
                    $errors["subjects.{$subjectId}.components"] = 'Component names must be different.';
                }

                $max = $components->isNotEmpty()
                    ? ResultCalculator::format($components->sum(fn ($c) => ResultCalculator::hundredths($c['max_marks'])))
                    : (string) ($input['max_marks'] ?? '');
                $pass = (string) ($input['pass_marks'] ?? '');

                if (! is_numeric($max) || (float) $max <= 0) {
                    $errors["subjects.{$subjectId}.max_marks"] = 'Enter the maximum marks.';

                    continue;
                }
                if (! is_numeric($pass) || (float) $pass < 0 || ResultCalculator::hundredths($pass) > ResultCalculator::hundredths($max)) {
                    $errors["subjects.{$subjectId}.pass_marks"] = 'Pass marks must be between 0 and the maximum ('.$max.').';

                    continue;
                }

                $newStructure = $components->map(fn ($c) => $c['name'].'|'.ResultCalculator::hundredths($c['max_marks']))->all();
                $oldStructure = $current?->components->map(fn ($c) => $c->name.'|'.ResultCalculator::hundredths($c->max_marks))->all() ?? [];
                $structureChanged = $current !== null && ($newStructure !== $oldStructure || ResultCalculator::hundredths($max) !== ResultCalculator::hundredths($current->max_marks));

                if ($structureChanged && in_array($current->getKey(), $withMarks['entered'], true)) {
                    $errors["subjects.{$subjectId}.max_marks"] = "{$name} already has marks entered: its maximum marks and components cannot change.";

                    continue;
                }

                $examSubject = $current ?? new ExamSubject([
                    'exam_id' => $exam->getKey(),
                    'school_class_id' => $class->getKey(),
                    'subject_id' => $subjectId,
                    'sort_order' => (int) ($offered[$subjectId] ?? 0),
                ]);
                $examSubject->fill([
                    'max_marks' => $max,
                    'pass_marks' => $pass,
                    'counts_in_total' => filter_var($input['counts_in_total'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]);
                $dirty = ! $examSubject->exists || $examSubject->isDirty();
                $examSubject->save();

                if ($current === null || $newStructure !== $oldStructure || $this->componentPassChanged($current, $components)) {
                    ExamComponent::query()->where('exam_subject_id', $examSubject->getKey())->delete();
                    foreach ($components as $i => $component) {
                        ExamComponent::create([
                            'exam_subject_id' => $examSubject->getKey(),
                            'name' => $component['name'],
                            'max_marks' => $component['max_marks'],
                            'pass_marks' => $component['pass_marks'],
                            'sort_order' => $i,
                        ]);
                    }
                    $dirty = true;
                }

                if ($dirty) {
                    $changes[$subjectId] = sprintf('max %s, pass %s%s', $max, $pass, $components->isNotEmpty() ? ', components: '.$components->map(fn ($c) => $c['name'].' '.$c['max_marks'])->implode(', ') : '');
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($changes !== []) {
                $this->audit->record('exam.subjects_updated', $exam, new: ['class' => $class->name, 'subjects' => $changes], actor: $actor);
            }
        });
    }

    /**
     * open_marks (draft -> marks_open), reopen_setup (marks_open -> draft), lock (marks_open|published -> locked).
     */
    public function transition(Exam $exam, string $action, User $actor): Exam
    {
        return DB::transaction(function () use ($exam, $action, $actor) {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
            $from = $exam->status;

            switch ($action) {
                case 'open_marks':
                    if (! $exam->isDraft()) {
                        throw ValidationException::withMessages(['action' => 'Marks entry is already open.']);
                    }
                    $this->assertReadyForMarks($exam);
                    $exam->forceFill(['status' => ExamStatus::MarksOpen, 'marks_opened_at' => $exam->marks_opened_at ?? now()]);
                    break;

                case 'reopen_setup':
                    if ($exam->status !== ExamStatus::MarksOpen) {
                        throw ValidationException::withMessages(['action' => 'Set-up can only be re-opened while marks entry is open and nothing is published.']);
                    }
                    $exam->forceFill(['status' => ExamStatus::Draft]);
                    break;

                case 'lock':
                    if (! in_array($exam->status, [ExamStatus::MarksOpen, ExamStatus::Published], true)) {
                        throw ValidationException::withMessages(['action' => $exam->isLocked() ? 'The exam is already locked.' : 'Open marks entry before locking the exam.']);
                    }
                    $exam->forceFill(['status' => ExamStatus::Locked, 'locked_at' => now(), 'locked_by' => $actor->getKey()]);
                    break;

                default:
                    throw ValidationException::withMessages(['action' => 'Unknown action.']);
            }

            $exam->forceFill(['updated_by' => $actor->getKey()])->save();
            $this->audit->record($action === 'lock' ? 'exam.locked' : 'exam.status_changed', $exam, ['status' => $from->value], ['status' => $exam->status->value], actor: $actor);

            return $exam;
        });
    }

    /**
     * Everything the exam overview needs, limited to the sections the user may see or mark.
     *
     * @return array<string, mixed>
     */
    public function overview(Exam $exam, User $user): array
    {
        $exam->loadMissing(['academicYear', 'gradingScheme']);

        $viewable = $this->access->viewableSectionIds($user);
        $markable = $this->access->markableSectionIds($user);
        $visible = $viewable === null || $markable === null ? null : array_values(array_unique([...$viewable, ...$markable]));

        $classes = $exam->classes()->get();
        $sections = Section::query()->with('schoolClass')
            ->whereIn('school_class_id', $classes->modelKeys())
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('sort_order')->orderBy('name')
            ->get()->groupBy('school_class_id');

        $subjects = ExamSubject::query()->with(['subject', 'components'])->where('exam_id', $exam->getKey())
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('school_class_id');

        $rosterCounts = StudentEnrollment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereIn('school_class_id', $classes->modelKeys())
            ->selectRaw('section_id, count(*) as aggregate')->groupBy('section_id')
            ->pluck('aggregate', 'section_id');

        $sheets = MarkSheet::query()->where('exam_id', $exam->getKey())->get()
            ->keyBy(fn (MarkSheet $s) => $s->exam_subject_id.':'.$s->section_id);
        $entered = Mark::query()->where('exam_id', $exam->getKey())->where('status', '!=', MarkStatus::Pending->value)
            ->selectRaw('mark_sheet_id, count(*) as aggregate')->groupBy('mark_sheet_id')
            ->pluck('aggregate', 'mark_sheet_id');

        $publications = ResultPublication::query()->active()->where('exam_id', $exam->getKey())->with('publishedBy')->get()->keyBy('school_class_id');

        $result = [];
        foreach ($classes as $class) {
            $classSections = $sections->get($class->getKey(), collect());
            if ($visible !== null && $classSections->isEmpty()) {
                continue;
            }

            $grid = [];
            foreach ($subjects->get($class->getKey(), collect()) as $examSubject) {
                $cells = [];
                foreach ($classSections as $section) {
                    $sheet = $sheets->get($examSubject->getKey().':'.$section->getKey());
                    $cells[$section->getKey()] = [
                        'total' => (int) ($rosterCounts[$section->getKey()] ?? 0),
                        'entered' => $sheet ? (int) ($entered[$sheet->getKey()] ?? 0) : 0,
                        'sheet' => $sheet,
                        'can_enter' => $this->access->canEnterMarks($user, (int) $section->getKey(), $examSubject),
                    ];
                }
                $grid[] = ['examSubject' => $examSubject, 'cells' => $cells];
            }

            $result[] = [
                'class' => $class,
                'sections' => $classSections,
                'subjects' => $grid,
                'publication' => $publications->get($class->getKey()),
                'students' => $classSections->sum(fn ($s) => (int) ($rosterCounts[$s->getKey()] ?? 0)),
            ];
        }

        return ['exam' => $exam, 'classes' => $result];
    }

    /**
     * Dashboard widget: upcoming exams and marks entry progress of exams that are open.
     *
     * @return array{upcoming: Collection<int, Exam>, progress: list<array{exam: Exam, entered: int, expected: int, percent: int}>}
     */
    public function dashboardSummary(): array
    {
        $today = school_today()->toDateString();

        $upcoming = Exam::query()->withCount('examClasses')
            ->where('status', '!=', ExamStatus::Locked->value)
            ->whereDate('ends_on', '>=', $today)
            ->orderBy('starts_on')->limit(4)->get();

        $open = Exam::query()->whereIn('status', [ExamStatus::MarksOpen->value, ExamStatus::Published->value])
            ->orderByDesc('starts_on')->limit(4)->get();

        $progress = [];
        foreach ($open as $exam) {
            $perClass = StudentEnrollment::query()
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('status', EnrollmentStatus::Active->value)
                ->selectRaw('school_class_id, count(*) as aggregate')->groupBy('school_class_id')
                ->pluck('aggregate', 'school_class_id');
            $expected = ExamSubject::query()->where('exam_id', $exam->getKey())->get(['school_class_id'])
                ->sum(fn (ExamSubject $s) => (int) ($perClass[$s->school_class_id] ?? 0));
            $entered = Mark::query()->where('exam_id', $exam->getKey())->where('status', '!=', MarkStatus::Pending->value)->count();

            $progress[] = [
                'exam' => $exam,
                'entered' => $entered,
                'expected' => $expected,
                'percent' => $expected > 0 ? (int) floor(min($entered, $expected) * 100 / $expected) : 0,
            ];
        }

        return ['upcoming' => $upcoming, 'progress' => $progress];
    }

    public function assertClassInExam(Exam $exam, int $classId): void
    {
        $exists = ExamClass::query()->where('exam_id', $exam->getKey())->where('school_class_id', $classId)->exists();
        if (! $exists) {
            throw new NotFoundHttpException('This class is not part of the exam.');
        }
    }

    private function addClass(Exam $exam, int $classId): void
    {
        ExamClass::create(['exam_id' => $exam->getKey(), 'school_class_id' => $classId]);

        $max = (string) $this->settings->get('default_max_marks', 100);
        $pass = (string) $this->settings->get('default_pass_marks', 33);
        if (ResultCalculator::hundredths($pass) > ResultCalculator::hundredths($max)) {
            $pass = $max;
        }

        foreach (ClassSubject::query()->where('school_class_id', $classId)->orderBy('sort_order')->get() as $classSubject) {
            ExamSubject::create([
                'exam_id' => $exam->getKey(),
                'school_class_id' => $classId,
                'subject_id' => $classSubject->subject_id,
                'max_marks' => $max,
                'pass_marks' => $pass,
                'counts_in_total' => true,
                'sort_order' => (int) $classSubject->sort_order,
            ]);
        }
    }

    /** @param  list<int>  $classIds */
    private function syncClasses(Exam $exam, array $classIds): void
    {
        $current = $this->classIds($exam);

        foreach (array_diff($current, $classIds) as $classId) {
            $subjectIds = ExamSubject::query()->where('exam_id', $exam->getKey())->where('school_class_id', $classId)->pluck('id')->all();
            if ($subjectIds !== [] && Mark::query()->whereIn('exam_subject_id', $subjectIds)->exists()) {
                $name = SchoolClass::query()->whereKey($classId)->value('name');
                throw ValidationException::withMessages(['class_ids' => "{$name} already has marks recorded, so it cannot be removed from the exam."]);
            }
            ExamComponent::query()->whereIn('exam_subject_id', $subjectIds)->delete();
            MarkSheet::query()->whereIn('exam_subject_id', $subjectIds)->delete();
            ExamSubject::query()->whereIn('id', $subjectIds)->delete();
            ExamClass::query()->where('exam_id', $exam->getKey())->where('school_class_id', $classId)->delete();
        }

        foreach (array_diff($classIds, $current) as $classId) {
            $this->addClass($exam, $classId);
        }
    }

    private function assertReadyForMarks(Exam $exam): void
    {
        $classIds = $this->classIds($exam);
        if ($classIds === []) {
            throw ValidationException::withMessages(['action' => 'Add at least one class to the exam first.']);
        }

        $withSubjects = ExamSubject::query()->where('exam_id', $exam->getKey())->distinct()->pluck('school_class_id')->map(fn ($id) => (int) $id)->all();
        $missing = array_diff($classIds, $withSubjects);
        if ($missing !== []) {
            $names = SchoolClass::query()->whereIn('id', $missing)->orderBy('sort_order')->pluck('name')->implode(', ');
            throw ValidationException::withMessages(['action' => "Choose the subjects to examine for: {$names}."]);
        }
    }

    /** @return list<int> */
    private function classIds(Exam $exam): array
    {
        return ExamClass::query()->where('exam_id', $exam->getKey())->orderBy('school_class_id')->pluck('school_class_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $examSubjectIds
     * @return array{any: list<int>, entered: list<int>}
     */
    private function subjectsWithMarks(array $examSubjectIds): array
    {
        if ($examSubjectIds === []) {
            return ['any' => [], 'entered' => []];
        }

        $rows = Mark::query()->whereIn('exam_subject_id', $examSubjectIds)
            ->selectRaw('exam_subject_id, sum(case when status = ? then 0 else 1 end) as entered', [MarkStatus::Pending->value])
            ->groupBy('exam_subject_id')->get();

        return [
            'any' => $rows->pluck('exam_subject_id')->map(fn ($id) => (int) $id)->all(),
            'entered' => $rows->filter(fn ($r) => (int) $r->entered > 0)->pluck('exam_subject_id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * @param  array{any: list<int>, entered: list<int>}  $withMarks
     * @return array<string, mixed>
     */
    private function subjectRow(int $subjectId, string $name, ?string $code, ?ExamSubject $examSubject, array $withMarks): array
    {
        return [
            'subject_id' => $subjectId,
            'name' => $name,
            'code' => $code,
            'included' => $examSubject !== null,
            'max_marks' => $examSubject ? ResultCalculator::format(ResultCalculator::hundredths($examSubject->max_marks)) : (string) $this->settings->get('default_max_marks', 100),
            'pass_marks' => $examSubject ? ResultCalculator::format(ResultCalculator::hundredths($examSubject->pass_marks)) : (string) $this->settings->get('default_pass_marks', 33),
            'counts_in_total' => $examSubject ? (bool) $examSubject->counts_in_total : true,
            'has_marks' => $examSubject !== null && in_array($examSubject->getKey(), $withMarks['entered'], true),
            'components' => $examSubject ? $examSubject->components->map(fn (ExamComponent $c) => [
                'name' => $c->name,
                'max_marks' => ResultCalculator::format(ResultCalculator::hundredths($c->max_marks)),
                'pass_marks' => $c->pass_marks !== null ? ResultCalculator::format(ResultCalculator::hundredths($c->pass_marks)) : null,
            ])->all() : [],
        ];
    }

    /** @param  Collection<int, array{name: string, max_marks: string, pass_marks: ?string}>  $components */
    private function componentPassChanged(ExamSubject $current, Collection $components): bool
    {
        $old = $current->components->map(fn ($c) => $c->pass_marks !== null ? ResultCalculator::hundredths($c->pass_marks) : null)->all();
        $new = $components->map(fn ($c) => $c['pass_marks'] !== null ? ResultCalculator::hundredths($c['pass_marks']) : null)->all();

        return $old !== $new;
    }

    /** @return array<string, mixed> */
    private function auditable(Exam $exam): array
    {
        return [
            'name' => $exam->name,
            'term' => $exam->term,
            'starts_on' => $exam->starts_on?->toDateString(),
            'ends_on' => $exam->ends_on?->toDateString(),
            'description' => $exam->description,
            'grading_scheme_id' => (int) $exam->grading_scheme_id,
            'classes' => $this->classIds($exam),
        ];
    }
}
