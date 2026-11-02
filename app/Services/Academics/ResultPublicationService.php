<?php

namespace App\Services\Academics;

use App\Enums\Academics\ExamStatus;
use App\Enums\Academics\PublicationStatus;
use App\Models\Academics\Exam;
use App\Models\Academics\ExamResult;
use App\Models\Academics\ResultPublication;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishing results (FR-510): writes an immutable snapshot (exam_results) for every student of the class and a
 * result_publications row (version, who, when, why). While a class is published its marks are read-only, so the
 * snapshot can only change by unpublishing (reason recorded), correcting and publishing a new version.
 * Earlier versions and their snapshots are kept as history.
 */
class ResultPublicationService
{
    public function __construct(
        private readonly ResultService $results,
        private readonly ExamService $exams,
        private readonly AuditLogger $audit,
    ) {}

    public function publish(Exam $exam, SchoolClass $class, ?string $reason, User $actor): ResultPublication
    {
        $this->exams->assertClassInExam($exam, (int) $class->getKey());

        return DB::transaction(function () use ($exam, $class, $reason, $actor) {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());

            if ($exam->status === ExamStatus::Draft) {
                throw ValidationException::withMessages(['publish' => 'Open marks entry and enter marks before publishing results.']);
            }
            if ($exam->status === ExamStatus::Locked) {
                throw ValidationException::withMessages(['publish' => 'This exam is locked.']);
            }
            if ($this->results->activePublication($exam, (int) $class->getKey()) !== null) {
                throw ValidationException::withMessages(['publish' => "Results for {$class->name} are already published. Unpublish them first to publish a corrected version."]);
            }

            $computed = $this->results->compute($exam, $class);
            if ($computed['subjects'] === []) {
                throw ValidationException::withMessages(['publish' => "No subjects are set up for {$class->name}."]);
            }
            if ($computed['rows'] === []) {
                throw ValidationException::withMessages(['publish' => "{$class->name} has no active students in this academic year."]);
            }
            if ($computed['incomplete'] > 0) {
                throw ValidationException::withMessages(['publish' => sprintf(
                    'Marks are missing for %d %s (%d %s in total). Enter them, or mark the students absent / not applicable / exempt.',
                    $computed['incomplete'], $computed['incomplete'] === 1 ? 'student' : 'students',
                    $computed['missing_marks'], $computed['missing_marks'] === 1 ? 'entry' : 'entries',
                )]);
            }

            $version = (int) ResultPublication::query()->where('exam_id', $exam->getKey())->where('school_class_id', $class->getKey())->max('version') + 1;

            $publication = ResultPublication::create([
                'exam_id' => $exam->getKey(),
                'school_class_id' => $class->getKey(),
                'version' => $version,
                'status' => PublicationStatus::Active,
                'active_flag' => 1,
                'students_count' => count($computed['rows']),
                'reason' => $reason,
                'options' => $computed['options'] + ['grading_scheme_id' => (int) $exam->grading_scheme_id],
                'published_by' => $actor->getKey(),
                'published_at' => now(),
            ]);

            foreach ($computed['rows'] as $row) {
                $result = $row['result'];
                ExamResult::create([
                    'result_publication_id' => $publication->getKey(),
                    'exam_id' => $exam->getKey(),
                    'school_class_id' => $class->getKey(),
                    'section_id' => $row['section_id'],
                    'student_enrollment_id' => $row['enrollment_id'],
                    'student_id' => $row['student_id'],
                    'student_name' => mb_substr($row['student_name'], 0, 170),
                    'admission_no' => $row['admission_no'],
                    'roll_no' => $row['roll_no'],
                    'total_obtained' => $result['total_obtained'],
                    'total_max' => $result['total_max'],
                    'percentage' => $result['percentage'],
                    'grade' => $result['grade'],
                    'grade_point' => $result['grade_point'],
                    'grade_remark' => $result['grade_remark'],
                    'passed' => (bool) $result['passed'],
                    'rank' => $row['rank'],
                    'subjects' => $result['subjects'],
                    'teacher_remark' => $row['remark'],
                ]);
            }

            if ($exam->status !== ExamStatus::Published) {
                $exam->forceFill(['status' => ExamStatus::Published, 'updated_by' => $actor->getKey()])->save();
            }

            $this->audit->record('results.published', $publication, new: [
                'exam' => $exam->name,
                'class' => $class->name,
                'version' => $version,
                'students' => count($computed['rows']),
            ], meta: array_filter(['reason' => $reason]), actor: $actor);

            return $publication;
        });
    }

    public function unpublish(Exam $exam, SchoolClass $class, string $reason, User $actor): ResultPublication
    {
        $this->exams->assertClassInExam($exam, (int) $class->getKey());

        return DB::transaction(function () use ($exam, $class, $reason, $actor) {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->getKey());
            if ($exam->status === ExamStatus::Locked) {
                throw ValidationException::withMessages(['unpublish' => 'This exam is locked: its published results are final.']);
            }

            $publication = ResultPublication::query()->active()->lockForUpdate()
                ->where('exam_id', $exam->getKey())->where('school_class_id', $class->getKey())->first();
            if ($publication === null) {
                throw ValidationException::withMessages(['unpublish' => "Results for {$class->name} are not published."]);
            }

            $publication->forceFill([
                'status' => PublicationStatus::Withdrawn,
                'active_flag' => null,
                'withdrawn_by' => $actor->getKey(),
                'withdrawn_at' => now(),
                'withdraw_reason' => $reason,
            ])->save();

            $stillPublished = ResultPublication::query()->active()->where('exam_id', $exam->getKey())->exists();
            if (! $stillPublished && $exam->status === ExamStatus::Published) {
                $exam->forceFill(['status' => ExamStatus::MarksOpen, 'updated_by' => $actor->getKey()])->save();
            }

            $this->audit->record('results.unpublished', $publication, ['status' => PublicationStatus::Active->value], [
                'status' => PublicationStatus::Withdrawn->value,
                'exam' => $exam->name,
                'class' => $class->name,
                'version' => $publication->version,
            ], ['reason' => $reason], $actor);

            return $publication;
        });
    }

    /** @return Collection<int, ResultPublication> newest first */
    public function history(Exam $exam): Collection
    {
        return ResultPublication::query()->with(['schoolClass', 'publishedBy', 'withdrawnBy'])
            ->where('exam_id', $exam->getKey())
            ->orderByDesc('published_at')->orderByDesc('id')
            ->get();
    }
}
