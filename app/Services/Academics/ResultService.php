<?php

namespace App\Services\Academics;

use App\Enums\Students\EnrollmentStatus;
use App\Models\Academics\Exam;
use App\Models\Academics\ExamRemark;
use App\Models\Academics\ExamResult;
use App\Models\Academics\ExamSubject;
use App\Models\Academics\Mark;
use App\Models\Academics\ResultPublication;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\Students\StudentEnrollment;
use Illuminate\Support\Collection;

/**
 * Class results: computed live from the marks (preview), or read from the immutable snapshot of the active
 * publication. Ranks are always computed over the whole class (or each section, per settings) before a teacher's
 * view is narrowed to their own sections, so a rank never depends on who is looking.
 */
class ResultService
{
    public function __construct(
        private readonly ResultCalculator $calculator,
        private readonly ExamSettings $settings,
    ) {}

    public function activePublication(Exam $exam, int $classId): ?ResultPublication
    {
        return ResultPublication::query()->active()->with('publishedBy')
            ->where('exam_id', $exam->getKey())->where('school_class_id', $classId)->first();
    }

    /**
     * Live results for every active student of the class.
     *
     * @param  list<int>|null  $sectionIds  narrow the returned rows (null = all)
     * @return array{subjects: list<array<string, mixed>>, rows: list<array<string, mixed>>, incomplete: int, missing_marks: int, options: array<string, mixed>}
     */
    public function compute(Exam $exam, SchoolClass $class, ?array $sectionIds = null): array
    {
        $exam->loadMissing('gradingScheme.bands');
        $options = $this->settings->calculationOptions();

        $examSubjects = ExamSubject::query()->with(['subject', 'components'])
            ->where('exam_id', $exam->getKey())->where('school_class_id', $class->getKey())
            ->orderBy('sort_order')->orderBy('id')->get();
        $rules = $examSubjects->map(fn (ExamSubject $s) => $s->rule())->values()->all();

        $roster = StudentEnrollment::query()->with(['student', 'section'])
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('school_class_id', $class->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->get()
            ->sortBy(fn (StudentEnrollment $e) => sprintf('%05d|%010d|%s', $e->section?->sort_order ?? 0, (int) $e->roll_no, $e->student->fullName()))
            ->values();

        $marks = Mark::query()->where('exam_id', $exam->getKey())
            ->whereIn('exam_subject_id', $examSubjects->modelKeys())
            ->whereIn('student_enrollment_id', $roster->modelKeys())
            ->get()->groupBy('student_enrollment_id');
        $remarks = ExamRemark::query()->where('exam_id', $exam->getKey())
            ->whereIn('student_enrollment_id', $roster->modelKeys())->pluck('remark', 'student_enrollment_id');

        $bands = $exam->gradingScheme->bandRules();

        $rows = [];
        $missing = 0;
        foreach ($roster as $enrollment) {
            $studentMarks = $marks->get($enrollment->getKey(), collect())
                ->mapWithKeys(fn (Mark $m) => [$m->exam_subject_id => $m->rule()])->all();
            $result = $this->calculator->calculate($rules, $studentMarks, $bands, $options);
            $missing += count($result['pending_subjects']);

            $rows[] = [
                'enrollment_id' => (int) $enrollment->getKey(),
                'student_id' => (int) $enrollment->student_id,
                'student_name' => $enrollment->student->fullName(),
                'admission_no' => $enrollment->student->admission_no,
                'roll_no' => $enrollment->roll_no,
                'section_id' => $enrollment->section_id !== null ? (int) $enrollment->section_id : null,
                'section' => $enrollment->section?->name,
                'result' => $result,
                'rank' => null,
                'remark' => $remarks[$enrollment->getKey()] ?? null,
            ];
        }

        if ($options['rank_enabled']) {
            $groups = $options['rank_scope'] === 'section'
                ? collect($rows)->groupBy(fn ($r) => (string) $r['section_id'], preserveKeys: true)
                : collect([$rows]);
            foreach ($groups as $group) {
                $ranks = $this->calculator->rank(collect($group)->map(fn ($r) => $r['result']['percentage_hundredths'])->all());
                foreach ($ranks as $index => $rank) {
                    $rows[$index]['rank'] = $rank;
                }
            }
        }

        $incomplete = collect($rows)->filter(fn ($r) => ! $r['result']['complete'])->count();

        if ($sectionIds !== null) {
            $rows = array_values(array_filter($rows, fn ($r) => in_array($r['section_id'], $sectionIds, true)));
        }

        return ['subjects' => $rules, 'rows' => $rows, 'incomplete' => $incomplete, 'missing_marks' => $missing, 'options' => $options];
    }

    /**
     * Snapshot rows of a publication.
     *
     * @param  list<int>|null  $sectionIds
     * @return Collection<int, ExamResult>
     */
    public function published(ResultPublication $publication, ?array $sectionIds = null): Collection
    {
        return ExamResult::query()->with('section')
            ->where('result_publication_id', $publication->getKey())
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->orderBy('id')
            ->get();
    }
}
