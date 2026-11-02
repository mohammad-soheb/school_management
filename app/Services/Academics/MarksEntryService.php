<?php

namespace App\Services\Academics;

use App\Enums\Academics\ExamStatus;
use App\Enums\Academics\MarkSheetStatus;
use App\Enums\Academics\MarkStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Models\Academics\Exam;
use App\Models\Academics\ExamRemark;
use App\Models\Academics\ExamSubject;
use App\Models\Academics\Mark;
use App\Models\Academics\MarkSheet;
use App\Models\Academics\ResultPublication;
use App\Models\SchoolSetup\Section;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Marks entry grid (FR-503, FR-508, FR-509): one section x one exam subject at a time.
 *
 *  - roster = active enrollments of the section in the exam's academic year, in roll order;
 *  - each cell is a number 0..max (or one number per component), or a flag: absent / not applicable / exempt;
 *    an empty cell stays "pending" (never guessed as 0);
 *  - saving is idempotent (one row per exam subject + enrollment, unchanged rows are not touched);
 *  - teachers reach only the subjects they teach in the section (ExamAccess); marks are read-only while the exam is a
 *    draft or locked, and while the class's results are published (unpublish to correct).
 */
class MarksEntryService
{
    private const NUMBER = '/^\d{1,4}(\.\d{1,2})?$/';

    public function __construct(
        private readonly ExamAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, StudentEnrollment> */
    public function roster(Exam $exam, Section $section): Collection
    {
        return StudentEnrollment::query()
            ->with('student')
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('section_id', $section->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->orderByRaw('roll_no IS NULL, CAST(roll_no AS UNSIGNED), roll_no, id')
            ->get();
    }

    /** Null when marks for the class can be changed, otherwise the reason they cannot. */
    public function lockReason(Exam $exam, int $classId): ?string
    {
        return match (true) {
            $exam->status === ExamStatus::Draft => 'Marks entry has not been opened for this exam yet.',
            $exam->status === ExamStatus::Locked => 'This exam is locked. Marks can no longer be changed.',
            ResultPublication::query()->active()->where('exam_id', $exam->getKey())->where('school_class_id', $classId)->exists() => 'Results for this class are published. Unpublish them to correct marks.',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function sheet(Exam $exam, Section $section, ExamSubject $examSubject, User $user): array
    {
        $this->assertSheet($exam, $section, $examSubject);
        $this->authorizeMarks($user, $section, $examSubject);

        $examSubject->loadMissing(['subject', 'components']);
        $section->loadMissing('schoolClass');
        $roster = $this->roster($exam, $section);
        $marks = Mark::query()->where('exam_subject_id', $examSubject->getKey())
            ->whereIn('student_enrollment_id', $roster->modelKeys())->get()->keyBy('student_enrollment_id');
        $sheet = MarkSheet::query()->with(['updatedBy', 'submittedBy'])
            ->where('exam_subject_id', $examSubject->getKey())->where('section_id', $section->getKey())->first();

        $otherSubjects = ExamSubject::query()->with('subject')
            ->where('exam_id', $exam->getKey())->where('school_class_id', $section->school_class_id)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (ExamSubject $s) => $this->access->canEnterMarks($user, (int) $section->getKey(), $s))
            ->values();

        return [
            'exam' => $exam,
            'section' => $section,
            'examSubject' => $examSubject,
            'roster' => $roster,
            'marks' => $marks,
            'sheet' => $sheet,
            'lockReason' => $this->lockReason($exam, (int) $section->school_class_id),
            'subjects' => $otherSubjects,
            'entered' => $marks->filter(fn (Mark $m) => $m->status !== MarkStatus::Pending)->count(),
        ];
    }

    /**
     * @param  array<int|string, array{flag?: ?string, value?: mixed, components?: array<int|string, mixed>}>  $entries  keyed by enrollment id
     * @return array{sheet: MarkSheet, changed: int}
     */
    public function save(Exam $exam, Section $section, ExamSubject $examSubject, array $entries, bool $submit, User $actor): array
    {
        $this->assertSheet($exam, $section, $examSubject);
        $this->authorizeMarks($actor, $section, $examSubject);

        $examSubject->loadMissing(['subject', 'components']);

        return DB::transaction(function () use ($exam, $section, $examSubject, $entries, $submit, $actor) {
            // Serialises with publish / lock: they take the same row lock.
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
            if ($reason = $this->lockReason($exam, (int) $section->school_class_id)) {
                throw ValidationException::withMessages(['marks' => $reason]);
            }

            $roster = $this->roster($exam, $section)->keyBy('id');
            $unknown = array_diff(array_map('intval', array_keys($entries)), $roster->keys()->all());
            if ($unknown !== []) {
                throw ValidationException::withMessages(['marks' => 'Some rows do not belong to this section. Reload the page and try again.']);
            }

            [$parsed, $errors] = $this->parse($entries, $examSubject);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $sheet = $this->sheetFor($exam, $section, $examSubject);
            $existing = Mark::query()->where('exam_subject_id', $examSubject->getKey())
                ->whereIn('student_enrollment_id', $roster->keys())->get()->keyBy('student_enrollment_id');

            $old = $new = [];
            foreach ($parsed as $enrollmentId => $value) {
                $mark = $existing->get($enrollmentId);
                $before = $mark ? $this->describe($mark->status, $mark->marks_obtained, $mark->component_marks) : $this->describe(MarkStatus::Pending, null, null);
                $after = $this->describe($value['status'], $value['obtained'], $value['components']);

                if ($before === $after) {
                    continue;
                }
                if ($mark === null && $value['status'] === MarkStatus::Pending) {
                    continue;
                }

                if ($mark === null) {
                    $mark = Mark::create([
                        'exam_id' => $exam->getKey(),
                        'exam_subject_id' => $examSubject->getKey(),
                        'mark_sheet_id' => $sheet->getKey(),
                        'student_enrollment_id' => $enrollmentId,
                        'student_id' => $roster[$enrollmentId]->student_id,
                        'status' => $value['status'],
                        'marks_obtained' => $value['obtained'],
                        'component_marks' => $value['components'],
                        'entered_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(),
                    ]);
                    $existing->put($enrollmentId, $mark);
                } else {
                    $mark->fill([
                        'mark_sheet_id' => $sheet->getKey(),
                        'status' => $value['status'],
                        'marks_obtained' => $value['obtained'],
                        'component_marks' => $value['components'],
                        'updated_by' => $actor->getKey(),
                    ])->save();
                }

                $old[$enrollmentId] = $before;
                $new[$enrollmentId] = $after;
            }

            if ($submit) {
                $missing = $roster->keys()->reject(fn ($id) => ($existing->get($id)?->status ?? MarkStatus::Pending) !== MarkStatus::Pending)->count();
                if ($missing > 0) {
                    throw ValidationException::withMessages(['marks' => "{$missing} ".($missing === 1 ? 'student has' : 'students have').' no marks yet. Enter marks or mark them absent / not applicable / exempt before submitting, or save as a draft.']);
                }
            }

            $statusBefore = $sheet->status;
            $sheet->fill([
                'status' => $submit ? MarkSheetStatus::Submitted : MarkSheetStatus::Draft,
                'submitted_at' => $submit ? ($statusBefore === MarkSheetStatus::Submitted && $new === [] ? $sheet->submitted_at : now()) : null,
                'submitted_by' => $submit ? $actor->getKey() : null,
            ]);
            if ($new !== [] || $sheet->isDirty()) {
                $sheet->updated_by = $actor->getKey();
                $sheet->save();
            }

            if ($new !== [] || $statusBefore !== $sheet->status) {
                $this->audit->record($submit ? 'marks.submitted' : 'marks.saved', $sheet, $old, $new, [
                    'exam_id' => $exam->getKey(),
                    'exam' => $exam->name,
                    'subject' => $examSubject->subject->name,
                    'section_id' => $section->getKey(),
                    'changed' => count($new),
                ], $actor);
            }

            return ['sheet' => $sheet, 'changed' => count($new)];
        });
    }

    /** @return array<string, mixed> */
    public function remarksSheet(Exam $exam, Section $section, User $user): array
    {
        $this->assertSectionInExam($exam, $section);
        if (! $this->access->canEnterRemarks($user, (int) $section->getKey())) {
            throw new AuthorizationException('You are not assigned to this section.');
        }

        $section->loadMissing('schoolClass');
        $roster = $this->roster($exam, $section);

        return [
            'exam' => $exam,
            'section' => $section,
            'roster' => $roster,
            'remarks' => ExamRemark::query()->where('exam_id', $exam->getKey())->whereIn('student_enrollment_id', $roster->modelKeys())->pluck('remark', 'student_enrollment_id'),
            'lockReason' => $this->lockReason($exam, (int) $section->school_class_id),
        ];
    }

    /** @param  array<int|string, ?string>  $remarks  keyed by enrollment id */
    public function saveRemarks(Exam $exam, Section $section, array $remarks, User $actor): int
    {
        $this->assertSectionInExam($exam, $section);
        if (! $this->access->canEnterRemarks($actor, (int) $section->getKey())) {
            throw new AuthorizationException('You are not assigned to this section.');
        }

        return DB::transaction(function () use ($exam, $section, $remarks, $actor) {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
            if ($reason = $this->lockReason($exam, (int) $section->school_class_id)) {
                throw ValidationException::withMessages(['remarks' => $reason]);
            }

            $roster = $this->roster($exam, $section)->keyBy('id');
            if (array_diff(array_map('intval', array_keys($remarks)), $roster->keys()->all()) !== []) {
                throw ValidationException::withMessages(['remarks' => 'Some rows do not belong to this section. Reload the page and try again.']);
            }

            $existing = ExamRemark::query()->where('exam_id', $exam->getKey())->whereIn('student_enrollment_id', $roster->keys())->get()->keyBy('student_enrollment_id');
            $old = $new = [];

            foreach ($remarks as $enrollmentId => $text) {
                $text = filled($text) ? trim((string) $text) : null;
                $row = $existing->get((int) $enrollmentId);
                if (($row?->remark) === $text || ($row === null && $text === null)) {
                    continue;
                }

                $old[$enrollmentId] = $row?->remark;
                $new[$enrollmentId] = $text;

                if ($row === null) {
                    ExamRemark::create(['exam_id' => $exam->getKey(), 'student_enrollment_id' => (int) $enrollmentId, 'remark' => $text, 'entered_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
                } else {
                    $row->fill(['remark' => $text, 'updated_by' => $actor->getKey()])->save();
                }
            }

            if ($new !== []) {
                $this->audit->record('exam_remarks.saved', $exam, $old, $new, ['section_id' => $section->getKey(), 'changed' => count($new)], $actor);
            }

            return count($new);
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $entries
     * @return array{0: array<int, array{status: MarkStatus, obtained: ?string, components: ?array<string, string>}>, 1: array<string, string>}
     */
    private function parse(array $entries, ExamSubject $examSubject): array
    {
        $components = $examSubject->components;
        $max = ResultCalculator::hundredths($examSubject->max_marks);
        $flags = [MarkStatus::Absent->value, MarkStatus::NotApplicable->value, MarkStatus::Exempt->value];
        $parsed = [];
        $errors = [];

        foreach ($entries as $enrollmentId => $entry) {
            $enrollmentId = (int) $enrollmentId;
            $flag = (string) ($entry['flag'] ?? '');

            if ($flag !== '') {
                if (! in_array($flag, $flags, true)) {
                    $errors["marks.{$enrollmentId}.flag"] = 'Choose absent, not applicable or exempt.';

                    continue;
                }
                $parsed[$enrollmentId] = ['status' => MarkStatus::from($flag), 'obtained' => null, 'components' => null];

                continue;
            }

            if ($components->isEmpty()) {
                $raw = trim((string) ($entry['value'] ?? ''));
                if ($raw === '') {
                    $parsed[$enrollmentId] = ['status' => MarkStatus::Pending, 'obtained' => null, 'components' => null];

                    continue;
                }
                if (! preg_match(self::NUMBER, $raw) || ResultCalculator::hundredths($raw) > $max) {
                    $errors["marks.{$enrollmentId}.value"] = 'Enter a number from 0 to '.ResultCalculator::format($max).' (up to 2 decimals).';

                    continue;
                }
                $parsed[$enrollmentId] = ['status' => MarkStatus::Present, 'obtained' => number_format(ResultCalculator::hundredths($raw) / 100, 2, '.', ''), 'components' => null];

                continue;
            }

            $values = [];
            $blank = 0;
            $rowOk = true;
            foreach ($components as $component) {
                $raw = trim((string) ($entry['components'][$component->getKey()] ?? ''));
                if ($raw === '') {
                    $blank++;

                    continue;
                }
                $componentMax = ResultCalculator::hundredths($component->max_marks);
                if (! preg_match(self::NUMBER, $raw) || ResultCalculator::hundredths($raw) > $componentMax) {
                    $errors["marks.{$enrollmentId}.components.{$component->getKey()}"] = $component->name.': enter a number from 0 to '.ResultCalculator::format($componentMax).'.';
                    $rowOk = false;

                    continue;
                }
                $values[(string) $component->getKey()] = number_format(ResultCalculator::hundredths($raw) / 100, 2, '.', '');
            }

            if (! $rowOk) {
                continue;
            }
            if ($blank === $components->count()) {
                $parsed[$enrollmentId] = ['status' => MarkStatus::Pending, 'obtained' => null, 'components' => null];

                continue;
            }
            if ($blank > 0) {
                $errors["marks.{$enrollmentId}.value"] = 'Enter every part ('.$components->pluck('name')->implode(', ').') or leave them all blank.';

                continue;
            }

            $sum = array_sum(array_map(fn ($v) => ResultCalculator::hundredths($v), $values));
            $parsed[$enrollmentId] = ['status' => MarkStatus::Present, 'obtained' => number_format($sum / 100, 2, '.', ''), 'components' => $values];
        }

        return [$parsed, $errors];
    }

    /** @param  array<string, mixed>|null  $components */
    private function describe(MarkStatus $status, mixed $obtained, ?array $components): string
    {
        return match ($status) {
            MarkStatus::Present => ResultCalculator::format(ResultCalculator::hundredths($obtained))
                .($components ? ' ('.collect($components)->map(fn ($v) => ResultCalculator::format(ResultCalculator::hundredths($v)))->implode('+').')' : ''),
            default => $status->value,
        };
    }

    private function sheetFor(Exam $exam, Section $section, ExamSubject $examSubject): MarkSheet
    {
        $attributes = ['exam_subject_id' => $examSubject->getKey(), 'section_id' => $section->getKey()];

        try {
            MarkSheet::query()->firstOrCreate($attributes, ['exam_id' => $exam->getKey(), 'status' => MarkSheetStatus::Draft]);
        } catch (UniqueConstraintViolationException) {
            // Created by a parallel request: lock and use it below.
        }

        return MarkSheet::query()->where($attributes)->lockForUpdate()->firstOrFail();
    }

    private function assertSheet(Exam $exam, Section $section, ExamSubject $examSubject): void
    {
        if ((int) $examSubject->exam_id !== (int) $exam->getKey() || (int) $examSubject->school_class_id !== (int) $section->school_class_id) {
            throw new NotFoundHttpException('This subject is not examined for this section.');
        }
    }

    private function assertSectionInExam(Exam $exam, Section $section): void
    {
        $inExam = ExamSubject::query()->where('exam_id', $exam->getKey())->where('school_class_id', $section->school_class_id)->exists()
            || $exam->examClasses()->where('school_class_id', $section->school_class_id)->exists();
        if (! $inExam) {
            throw new NotFoundHttpException('This section is not part of the exam.');
        }
    }

    private function authorizeMarks(User $user, Section $section, ExamSubject $examSubject): void
    {
        if (! $this->access->canEnterMarks($user, (int) $section->getKey(), $examSubject)) {
            throw new AuthorizationException('You can only enter marks for the subjects you teach in your sections.');
        }
    }
}
