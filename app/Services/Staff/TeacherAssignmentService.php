<?php

namespace App\Services\Staff;

use App\Enums\Staff\StaffStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Identity\AssignmentScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which staff member teaches which class/section/subject in a year (FR-208). These rows decide what a teacher can
 * see and do in students, attendance, marks and homework (AssignmentScope), so every change is audited.
 */
class TeacherAssignmentService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly AssignmentScope $scope,
    ) {}

    public function currentYear(): ?AcademicYear
    {
        return $this->context->academicYear();
    }

    /** @return Collection<int, TeacherAssignment> a staff member's assignments for a year (default: current) */
    public function forStaff(StaffProfile $staff, ?int $yearId = null): Collection
    {
        return TeacherAssignment::query()
            ->where('staff_profile_id', $staff->getKey())
            ->where('academic_year_id', $yearId ?? $this->context->academicYear()?->getKey() ?? 0)
            ->with(['schoolClass', 'section', 'subject'])
            ->get()
            ->sortBy(fn (TeacherAssignment $a) => sprintf('%05d-%s-%d-%s', $a->schoolClass->sort_order, $a->schoolClass->name, $a->section?->sort_order ?? 0, $a->subject?->name ?? ''))
            ->values();
    }

    /**
     * Every assignment of a year grouped by section ("Class 5 - A" => rows), optionally for one class.
     *
     * @return Collection<string, Collection<int, TeacherAssignment>>
     */
    public function bySection(?int $yearId = null, ?int $classId = null): Collection
    {
        return TeacherAssignment::query()
            ->where('academic_year_id', $yearId ?? $this->context->academicYear()?->getKey() ?? 0)
            ->when($classId !== null, fn ($q) => $q->where('school_class_id', $classId))
            ->with(['schoolClass', 'section', 'subject', 'staffProfile'])
            ->get()
            ->sortBy(fn (TeacherAssignment $a) => sprintf('%05d-%s-%05d-%d-%s', $a->schoolClass->sort_order, $a->schoolClass->name, $a->section?->sort_order ?? 0, $a->is_class_teacher ? 0 : 1, $a->subject?->name ?? ''))
            ->groupBy(fn (TeacherAssignment $a) => $a->schoolClass->name.($a->section ? ' - '.$a->section->name : ' (whole class)'));
    }

    /**
     * @param  array{academic_year_id?: ?int, school_class_id: int, section_id?: ?int, subject_id?: ?int, is_class_teacher?: bool}  $data
     */
    public function assign(StaffProfile $staff, array $data, User $actor): TeacherAssignment
    {
        return DB::transaction(function () use ($staff, $data, $actor): TeacherAssignment {
            // Lock the staff row: two quick submits of the same assignment cannot both pass the duplicate check.
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());

            if ($staff->status !== StaffStatus::Active) {
                throw ValidationException::withMessages(['school_class_id' => 'Only active staff can be given classes.']);
            }

            $year = $this->resolveYear($data['academic_year_id'] ?? null);
            $class = SchoolClass::query()->findOrFail((int) $data['school_class_id']);
            $sectionId = ! empty($data['section_id']) ? (int) $data['section_id'] : null;
            $subjectId = ! empty($data['subject_id']) ? (int) $data['subject_id'] : null;
            $isClassTeacher = (bool) ($data['is_class_teacher'] ?? false);

            if ($sectionId !== null && ! Section::query()->whereKey($sectionId)->where('school_class_id', $class->getKey())->exists()) {
                throw ValidationException::withMessages(['section_id' => 'Choose a section of the selected class.']);
            }

            if ($subjectId !== null) {
                Subject::query()->findOrFail($subjectId);
                $classHasSubjects = ClassSubject::query()->where('school_class_id', $class->getKey())->exists();
                if ($classHasSubjects && ! ClassSubject::query()->where('school_class_id', $class->getKey())->where('subject_id', $subjectId)->exists()) {
                    throw ValidationException::withMessages(['subject_id' => "This subject is not taught in {$class->name}."]);
                }
            }

            if ($subjectId === null && ! $isClassTeacher) {
                throw ValidationException::withMessages(['subject_id' => 'Choose a subject, or tick "Class teacher".']);
            }

            if ($isClassTeacher && $sectionId === null) {
                throw ValidationException::withMessages(['section_id' => 'A class teacher is assigned to a section.']);
            }

            $duplicate = TeacherAssignment::query()
                ->where('staff_profile_id', $staff->getKey())
                ->where('academic_year_id', $year->getKey())
                ->where('school_class_id', $class->getKey())
                ->when($sectionId === null, fn ($q) => $q->whereNull('section_id'), fn ($q) => $q->where('section_id', $sectionId))
                ->when($subjectId === null, fn ($q) => $q->whereNull('subject_id'), fn ($q) => $q->where('subject_id', $subjectId))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['school_class_id' => 'This assignment already exists.']);
            }

            if ($isClassTeacher) {
                $existing = TeacherAssignment::query()
                    ->where('academic_year_id', $year->getKey())
                    ->where('section_id', $sectionId)
                    ->where('is_class_teacher', true)
                    ->with('staffProfile')
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw ValidationException::withMessages(['is_class_teacher' => "{$existing->staffProfile->fullName()} is already the class teacher of this section."]);
                }
            }

            $assignment = TeacherAssignment::create([
                'staff_profile_id' => $staff->getKey(),
                'academic_year_id' => $year->getKey(),
                'school_class_id' => $class->getKey(),
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'is_class_teacher' => $isClassTeacher,
            ]);

            $this->audit->record('teacher_assignment.created', $assignment, new: $assignment->only(['staff_profile_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'is_class_teacher']), actor: $actor);
            $this->scope->forget();

            return $assignment;
        });
    }

    /** Assignments carry no history of their own (attendance/marks keep who entered them), so removal is a delete. */
    public function remove(TeacherAssignment $assignment, User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $assignment = TeacherAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $year = AcademicYear::query()->findOrFail($assignment->academic_year_id);

            if ($year->isLocked()) {
                throw ValidationException::withMessages(['assignment' => "Academic year {$year->name} is locked."]);
            }

            $old = $assignment->only(['staff_profile_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'is_class_teacher']);
            $assignment->delete();

            $this->audit->record('teacher_assignment.removed', $assignment, old: $old, actor: $actor);
            $this->scope->forget();
        });
    }

    /**
     * Choices for the assignment form: years, classes with their sections, and subjects per class.
     *
     * @return array{years: array<int, string>, classes: array<int, string>, sections: array<int, array<int, string>>, subjects: array<int, string>, classSubjects: array<int, list<int>>}
     */
    public function formOptions(): array
    {
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $sections = Section::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'school_class_id', 'name']);

        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section->school_class_id][$section->id] = $section->name;
        }

        $classSubjects = [];
        foreach (ClassSubject::query()->get(['school_class_id', 'subject_id']) as $row) {
            $classSubjects[$row->school_class_id][] = (int) $row->subject_id;
        }

        return [
            'years' => AcademicYear::query()->where('status', '!=', 'locked')->orderByDesc('starts_on')->pluck('name', 'id')->all(),
            'classes' => $classes->pluck('name', 'id')->all(),
            'sections' => $sectionMap,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'classSubjects' => $classSubjects,
        ];
    }

    private function resolveYear(?int $yearId): AcademicYear
    {
        $year = $yearId !== null ? AcademicYear::query()->find($yearId) : $this->context->academicYear();

        if ($year === null) {
            throw ValidationException::withMessages(['academic_year_id' => 'Set the current academic year (School setup) first.']);
        }

        if ($year->isLocked()) {
            throw ValidationException::withMessages(['academic_year_id' => "Academic year {$year->name} is locked."]);
        }

        return $year;
    }
}
