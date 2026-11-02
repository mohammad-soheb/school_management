<?php

namespace App\Services\SchoolSetup;

use App\Enums\Staff\StaffStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\Staff\StaffProfile;
use App\Support\Tenancy\TenantContext;

/**
 * Numbers and the onboarding checklist for the "School setup" overview page (and the setup dashboard widget).
 */
class SetupOverviewService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array{years: int, classes: int, sections: int, subjects: int, holidays: int, current_year: ?AcademicYear}
     */
    public function counts(): array
    {
        $year = $this->context->academicYear();

        return [
            'years' => AcademicYear::query()->count(),
            'classes' => SchoolClass::query()->where('is_active', true)->count(),
            'sections' => Section::query()->where('is_active', true)->count(),
            'subjects' => Subject::query()->where('is_active', true)->count(),
            'holidays' => $year === null ? 0 : SchoolHoliday::query()->where('academic_year_id', $year->getKey())->count(),
            'current_year' => $year,
        ];
    }

    /**
     * Ordered onboarding steps. Each: key, label, hint, done, route (name), permission needed to act on it.
     *
     * @return list<array{key: string, label: string, hint: string, done: bool, route: string, can: string}>
     */
    public function checklist(): array
    {
        $school = $this->context->requireSchool();
        $year = $this->context->academicYear();
        $classesWithoutSections = SchoolClass::query()->where('is_active', true)->whereDoesntHave('sections')->count();
        $classCount = SchoolClass::query()->where('is_active', true)->count();
        $sectionCount = Section::query()->where('is_active', true)->count();

        return [
            [
                'key' => 'profile',
                'label' => 'Complete the school profile',
                'hint' => 'Contact details, address and logo appear on receipts and report cards.',
                'done' => filled($school->phone) && filled($school->address_line1) && filled($school->city),
                'route' => 'school.setup.profile.edit',
                'can' => 'setup.profile.update',
            ],
            [
                'key' => 'year',
                'label' => 'Set the current academic year',
                'hint' => 'Attendance, fees and exams all work inside the current year.',
                'done' => $year !== null,
                'route' => 'school.setup.years.index',
                'can' => 'setup.years.manage',
            ],
            [
                'key' => 'classes',
                'label' => 'Add classes and sections',
                'hint' => $classesWithoutSections > 0 ? "{$classesWithoutSections} active ".($classesWithoutSections === 1 ? 'class has' : 'classes have').' no section yet.' : 'For example Nursery to Class 10, each with sections A, B ...',
                'done' => $classCount > 0 && $sectionCount > 0 && $classesWithoutSections === 0,
                'route' => 'school.setup.classes.index',
                'can' => 'setup.classes.manage',
            ],
            [
                'key' => 'subjects',
                'label' => 'Add subjects and map them to classes',
                'hint' => 'Exams and teacher assignments use the subjects taught in each class.',
                'done' => Subject::query()->where('is_active', true)->exists() && ClassSubject::query()->exists(),
                'route' => 'school.setup.subjects.index',
                'can' => 'setup.subjects.manage',
            ],
            [
                'key' => 'class_teachers',
                'label' => 'Choose class teachers',
                'hint' => StaffProfile::query()->where('status', StaffStatus::Active->value)->exists()
                    ? 'Pick a class teacher for every section.'
                    : 'Add staff members first, then pick a class teacher for every section.',
                'done' => $sectionCount > 0 && ! Section::query()->where('is_active', true)->whereNull('class_teacher_id')->exists(),
                'route' => 'school.setup.classes.index',
                'can' => 'setup.classes.manage',
            ],
            [
                'key' => 'calendar',
                'label' => 'Add this year\'s holidays',
                'hint' => 'Holidays and weekly offs are excluded from attendance working days.',
                'done' => $year !== null && SchoolHoliday::query()->where('academic_year_id', $year->getKey())->exists(),
                'route' => 'school.setup.holidays.index',
                'can' => 'setup.holidays.manage',
            ],
        ];
    }
}
