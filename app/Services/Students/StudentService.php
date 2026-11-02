<?php

namespace App\Services\Students;

use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;
use App\Enums\Students\StudentStatusAction;
use App\Models\Documents\StoredFile;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\Students\StudentStatusChange;
use App\Models\User;
use App\Services\Documents\PrivateFileService;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Write side of the student register (FR-203, FR-207, FR-209, FR-2B01): admission, edits, photo and status changes.
 * A student is never deleted; class/section/roll number live on the per-year enrollment.
 */
class StudentService
{
    /** Personal fields a user may edit on the student row. */
    public const STUDENT_FIELDS = ['admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'blood_group', 'admission_date', 'phone', 'email', 'address', 'previous_school'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly GuardianService $guardians,
        private readonly PrivateFileService $files,
    ) {}

    /**
     * Admit a student: the student row, the enrollment in the CURRENT academic year and the guardian links, in one
     * transaction. Each guardian entry either names an existing guardian (`id`, e.g. a sibling's parent) or carries
     * the details of a new one.
     *
     * @param  array<string, mixed>  $data  validated AdmitStudentRequest data
     */
    public function admit(array $data, User $actor): Student
    {
        $year = $this->requireOpenCurrentYear();

        return DB::transaction(function () use ($data, $actor, $year): Student {
            // Serialises admissions per school so the duplicate checks below cannot race (the unique index is the backstop).
            $this->context->requireSchool()->newQuery()->whereKey($this->context->requireId())->lockForUpdate()->first();

            $this->assertAdmissionNoFree($data['admission_no']);
            $section = $this->resolveSection((int) $data['school_class_id'], $data['section_id'] ?? null);
            $this->assertRollNoFree($year->getKey(), $section?->getKey(), $data['roll_no'] ?? null);

            $student = Student::create([
                ...Arr::only($data, self::STUDENT_FIELDS),
                'admission_date' => $data['admission_date'] ?? school_today()->toDateString(),
                'status' => StudentStatus::Active,
            ]);

            $enrollment = StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'academic_year_id' => $year->getKey(),
                'school_class_id' => (int) $data['school_class_id'],
                'section_id' => $section?->getKey(),
                'roll_no' => $data['roll_no'] ?? null,
                'status' => EnrollmentStatus::Active,
                'joined_on' => $student->admission_date?->toDateString() ?? school_today()->toDateString(),
            ]);

            foreach (array_values($data['guardians'] ?? []) as $index => $entry) {
                $this->guardians->link($student, [...$entry, 'is_primary' => $index === 0], $actor, audit: false);
            }

            $this->audit->record('student.admitted', $student, new: [
                'admission_no' => $student->admission_no,
                'academic_year_id' => $year->getKey(),
                'school_class_id' => $enrollment->school_class_id,
                'section_id' => $enrollment->section_id,
                'roll_no' => $enrollment->roll_no,
                'guardians' => count($data['guardians'] ?? []),
            ], actor: $actor);

            return $student;
        });
    }

    /**
     * Edit the student's personal details and, when given, the current-year class/section/roll number.
     *
     * @param  array<string, mixed>  $data  validated UpdateStudentRequest data
     */
    public function update(Student $student, array $data, User $actor): Student
    {
        return DB::transaction(function () use ($student, $data, $actor): Student {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());

            if (($data['admission_no'] ?? $student->admission_no) !== $student->admission_no) {
                $this->assertAdmissionNoFree($data['admission_no'], $student->getKey());
            }

            $before = $this->snapshot($student);
            $student->fill(Arr::only($data, self::STUDENT_FIELDS))->save();
            [$old, $new] = $this->audit->diff($before, $this->snapshot($student));
            // Contact details of a child are personal data: the audit says THAT they changed, not what they were.
            foreach (['phone', 'email', 'address', 'date_of_birth'] as $private) {
                if (array_key_exists($private, $new)) {
                    $old[$private] = $new[$private] = '[changed]';
                }
            }

            if (array_key_exists('school_class_id', $data) && $data['school_class_id'] !== null) {
                [$placementOld, $placementNew] = $this->updatePlacement($student, $data);
                $old += $placementOld;
                $new += $placementNew;
            }

            if ($new !== []) {
                $this->audit->record('student.updated', $student, $old, $new, actor: $actor);
            }

            return $student;
        });
    }

    /**
     * Withdraw, transfer, deactivate or reactivate a student (FR-207). The student row keeps only the current status;
     * the change itself (date, reason, who) is appended to student_status_changes and audited. Withdraw/transfer close
     * this year's enrollment on the effective date; reactivate reopens it. Nothing is deleted.
     *
     * @param  array{effective_on: string, reason: string}  $data
     */
    public function changeStatus(Student $student, StudentStatusAction $action, array $data, User $actor): StudentStatusChange
    {
        $effectiveOn = CarbonImmutable::parse($data['effective_on'])->startOfDay();
        if ($effectiveOn->greaterThan(school_today())) {
            throw ValidationException::withMessages(['effective_on' => 'The date cannot be in the future.']);
        }

        return DB::transaction(function () use ($student, $action, $data, $actor, $effectiveOn): StudentStatusChange {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $from = $student->status;

            if (! in_array($action, StudentStatusAction::availableFor($from), true)) {
                throw ValidationException::withMessages(['action' => "A student who is {$from->label()} cannot be changed with \"{$action->label()}\"."]);
            }

            if ($student->admission_date !== null && $effectiveOn->lessThan($student->admission_date)) {
                throw ValidationException::withMessages(['effective_on' => 'The date cannot be before the admission date ('.$student->admission_date->format('d M Y').').']);
            }

            $enrollment = $this->currentEnrollmentFor($student, lock: true);
            $enrollmentStatus = $action->enrollmentStatus();

            if ($enrollment !== null && $enrollmentStatus !== null) {
                if ($enrollmentStatus === EnrollmentStatus::Active) {
                    // Reopen only an enrollment this workflow closed; a promoted/completed year stays as it is.
                    if (in_array($enrollment->status, [EnrollmentStatus::Withdrawn, EnrollmentStatus::Transferred], true)) {
                        $enrollment->forceFill(['status' => EnrollmentStatus::Active, 'left_on' => null, 'exit_reason' => null])->save();
                    }
                } elseif ($enrollment->status === EnrollmentStatus::Active) {
                    if ($enrollment->joined_on !== null && $effectiveOn->lessThan($enrollment->joined_on)) {
                        throw ValidationException::withMessages(['effective_on' => 'The date cannot be before the student joined this class ('.$enrollment->joined_on->format('d M Y').').']);
                    }
                    $enrollment->forceFill([
                        'status' => $enrollmentStatus,
                        'left_on' => $effectiveOn->toDateString(),
                        'exit_reason' => mb_substr($data['reason'], 0, 255),
                    ])->save();
                }
            }

            $student->forceFill(['status' => $action->targetStatus()])->save();

            $change = StudentStatusChange::create([
                'student_id' => $student->getKey(),
                'student_enrollment_id' => $enrollment?->getKey(),
                'from_status' => $from,
                'to_status' => $action->targetStatus(),
                'effective_on' => $effectiveOn->toDateString(),
                'reason' => $data['reason'],
                'changed_by' => $actor->getKey(),
            ]);

            $this->audit->record('student.status_changed', $student,
                old: ['status' => $from->value],
                new: ['status' => $action->targetStatus()->value],
                meta: ['action' => $action->value, 'effective_on' => $effectiveOn->toDateString(), 'enrollment_id' => $enrollment?->getKey()],
                actor: $actor,
            );

            return $change;
        });
    }

    /** Replace the student's photo (purpose student_photo, private, served through school.files.show). */
    public function updatePhoto(Student $student, UploadedFile $photo, User $actor): StoredFile
    {
        return DB::transaction(function () use ($student, $photo, $actor): StoredFile {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $old = $student->photo_file_id !== null ? StoredFile::query()->find($student->photo_file_id) : null;

            $stored = $this->files->store($photo, 'student_photo', $student, $actor);
            $student->forceFill(['photo_file_id' => $stored->getKey()])->save();

            if ($old !== null) {
                $this->files->delete($old, $actor);
            }

            $this->audit->record('student.photo_updated', $student, new: ['photo_file_id' => $stored->getKey()], actor: $actor);

            return $stored;
        });
    }

    public function removePhoto(Student $student, User $actor): void
    {
        DB::transaction(function () use ($student, $actor): void {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            if ($student->photo_file_id === null) {
                return;
            }

            $file = StoredFile::query()->find($student->photo_file_id);
            $student->forceFill(['photo_file_id' => null])->save();
            if ($file !== null) {
                $this->files->delete($file, $actor);
            }

            $this->audit->record('student.photo_removed', $student, actor: $actor);
        });
    }

    public function currentEnrollmentFor(Student $student, bool $lock = false): ?StudentEnrollment
    {
        $yearId = $this->context->academicYear()?->getKey();
        if ($yearId === null) {
            return null;
        }

        return StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('academic_year_id', $yearId)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();
    }

    public function requireOpenCurrentYear(): AcademicYear
    {
        $year = $this->context->academicYear();

        if ($year === null) {
            throw ValidationException::withMessages(['school_class_id' => 'Set the current academic year (School setup) before admitting students.']);
        }

        if ($year->isLocked()) {
            throw ValidationException::withMessages(['school_class_id' => "Academic year {$year->name} is locked. Reopen it before changing enrollments."]);
        }

        return $year;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} audit old/new for the placement */
    private function updatePlacement(Student $student, array $data): array
    {
        $enrollment = $this->currentEnrollmentFor($student, lock: true);
        $classId = (int) $data['school_class_id'];
        $section = $this->resolveSection($classId, $data['section_id'] ?? null);
        $rollNo = $data['roll_no'] ?? null;

        if ($enrollment === null) {
            // Student has no place this year (e.g. admitted last year and not promoted): create one.
            $year = $this->requireOpenCurrentYear();
            $this->assertRollNoFree($year->getKey(), $section?->getKey(), $rollNo);
            $enrollment = StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'academic_year_id' => $year->getKey(),
                'school_class_id' => $classId,
                'section_id' => $section?->getKey(),
                'roll_no' => $rollNo,
                'status' => $student->status === StudentStatus::Active ? EnrollmentStatus::Active : EnrollmentStatus::Withdrawn,
                'joined_on' => school_today()->toDateString(),
            ]);

            return [['enrollment' => null], ['enrollment' => ['school_class_id' => $classId, 'section_id' => $section?->getKey(), 'roll_no' => $rollNo]]];
        }

        $before = ['school_class_id' => $enrollment->school_class_id, 'section_id' => $enrollment->section_id, 'roll_no' => $enrollment->roll_no];
        $after = ['school_class_id' => $classId, 'section_id' => $section?->getKey(), 'roll_no' => $rollNo];

        if ($before == $after) {
            return [[], []];
        }

        $this->requireOpenCurrentYear();
        $this->assertRollNoFree($enrollment->academic_year_id, $section?->getKey(), $rollNo, $enrollment->getKey());
        $enrollment->forceFill($after)->save();

        return $this->audit->diff($before, $after);
    }

    private function resolveSection(int $classId, mixed $sectionId): ?Section
    {
        if ($sectionId === null || $sectionId === '') {
            return null;
        }

        $section = Section::query()->whereKey((int) $sectionId)->first();
        if ($section === null || (int) $section->school_class_id !== $classId) {
            throw ValidationException::withMessages(['section_id' => 'Choose a section of the selected class.']);
        }

        return $section;
    }

    private function assertAdmissionNoFree(string $admissionNo, ?int $ignoreId = null): void
    {
        $taken = Student::query()
            ->where('admission_no', $admissionNo)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['admission_no' => 'This admission number is already used in this school.']);
        }
    }

    private function assertRollNoFree(int $yearId, ?int $sectionId, ?string $rollNo, ?int $ignoreEnrollmentId = null): void
    {
        if ($rollNo === null || $rollNo === '' || $sectionId === null) {
            return;
        }

        $taken = StudentEnrollment::query()
            ->where('academic_year_id', $yearId)
            ->where('section_id', $sectionId)
            ->where('roll_no', $rollNo)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($ignoreEnrollmentId !== null, fn ($q) => $q->whereKeyNot($ignoreEnrollmentId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['roll_no' => "Roll number {$rollNo} is already taken in this section."]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Student $student): array
    {
        $values = [];
        foreach (self::STUDENT_FIELDS as $field) {
            $value = $student->getAttribute($field);
            $values[$field] = match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                $value instanceof \BackedEnum => $value->value,
                default => $value,
            };
        }

        return $values;
    }
}
