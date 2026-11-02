<?php

namespace Database\Seeders;

use App\Enums\Shared\Gender;
use App\Enums\Students\GuardianLinkStatus;
use App\Enums\Students\GuardianRelation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Platform\School;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\Students\StudentGuardian;
use App\Models\User;
use App\Services\Identity\RoleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Two synthetic demo schools for local development and manual testing. All names, phones and emails are made up.
 *
 * Logins (password "password" everywhere):
 *   admin@sunrise.test · teacher@sunrise.test · accountant@sunrise.test · parent@sunrise.test   (Sunrise Public School)
 *   admin@greenvalley.test · teacher@greenvalley.test · ...                                    (Green Valley Academy)
 *
 * Modules add their own demo data by dropping a seeder into database/seeders/Demo/ (any *Seeder.php there is run
 * after the core data, in file-name order). A module seeder finds the demo schools by code ("sunrise", "greenvalley")
 * and runs its inserts inside app(TenantContext::class)->run($school, fn () => ...).
 */
class DemoSeeder extends Seeder
{
    private const MALE = ['Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Reyansh', 'Krishna', 'Ishaan', 'Rohan', 'Kabir', 'Dev', 'Yash', 'Harsh', 'Nikhil', 'Manav', 'Tanmay', 'Aryan'];

    private const FEMALE = ['Ananya', 'Diya', 'Saanvi', 'Aadhya', 'Kavya', 'Ira', 'Myra', 'Riya', 'Pari', 'Navya', 'Tara', 'Isha', 'Meera', 'Shreya', 'Anika', 'Prisha'];

    private const SURNAMES = ['Sharma', 'Verma', 'Patel', 'Jain', 'Gupta', 'Agrawal', 'Chouhan', 'Mishra', 'Joshi', 'Tiwari', 'Yadav', 'Rathore', 'Solanki', 'Malviya', 'Dubey', 'Bhatt'];

    private const CLASSES = ['Nursery', 'LKG', 'UKG', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'];

    private const SUBJECTS = ['English', 'Hindi', 'Mathematics', 'Science', 'Social Science', 'Computer', 'Art'];

    public function run(): void
    {
        mt_srand(2026); // same demo data every time

        foreach ([
            ['name' => 'Sunrise Public School', 'code' => 'sunrise', 'prefix' => 'SUN', 'domain' => 'sunrise.test', 'area' => 'Khajrana'],
            ['name' => 'Green Valley Academy', 'code' => 'greenvalley', 'prefix' => 'GVA', 'domain' => 'greenvalley.test', 'area' => 'Vijay Nagar'],
        ] as $spec) {
            $this->seedSchool($spec);
        }

        foreach (glob(__DIR__.'/Demo/*Seeder.php') ?: [] as $file) {
            $this->call('Database\\Seeders\\Demo\\'.basename($file, '.php'));
        }
    }

    /** @param  array{name: string, code: string, prefix: string, domain: string, area: string}  $spec */
    private function seedSchool(array $spec): void
    {
        if (School::query()->where('code', $spec['code'])->exists()) {
            $this->command?->line("{$spec['name']} already exists - skipped.");

            return;
        }

        $school = School::create([
            'name' => $spec['name'], 'code' => $spec['code'], 'board' => 'CBSE',
            'email' => 'office@'.$spec['domain'], 'phone' => '0731-4000'.mt_rand(100, 999),
            'address_line1' => '12 School Road, '.$spec['area'], 'city' => 'Indore', 'state' => 'Madhya Pradesh', 'pincode' => '452010',
        ]);
        app(RoleService::class)->seedDefaultRoles($school);

        app(TenantContext::class)->run($school, function () use ($spec) {
            $year = AcademicYear::create(['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true]);

            $classes = $this->classes();
            $subjects = $this->subjects($classes);
            $staff = $this->staff($spec);
            $sections = $this->sections($classes, $staff);
            $this->assignTeachers($year, $staff, $sections, $subjects);
            $this->holidays($year);
            $this->members($spec, $staff);
            $this->students($spec, $year, $sections);
        });

        $this->command?->info("Seeded {$spec['name']}  (sign in as admin@{$spec['domain']} / password)");
    }

    /** @return array<string, SchoolClass> keyed by name */
    private function classes(): array
    {
        $classes = [];
        foreach (self::CLASSES as $i => $name) {
            $classes[$name] = SchoolClass::create(['name' => $name, 'code' => (string) ($i + 1), 'sort_order' => $i + 1]);
        }
        foreach (array_values($classes) as $i => $class) {
            $class->forceFill(['next_class_id' => array_values($classes)[$i + 1]->id ?? null])->save();
        }

        return $classes;
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @return list<Subject>
     */
    private function subjects(array $classes): array
    {
        $subjects = [];
        foreach (self::SUBJECTS as $i => $name) {
            $subjects[] = Subject::create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3)), 'sort_order' => $i + 1]);
        }

        foreach ($classes as $className => $class) {
            $junior = in_array($className, ['Nursery', 'LKG', 'UKG'], true);
            foreach ($subjects as $i => $subject) {
                if ($junior && ! in_array($subject->name, ['English', 'Hindi', 'Mathematics', 'Art'], true)) {
                    continue;
                }
                ClassSubject::create(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'sort_order' => $i + 1]);
            }
        }

        return $subjects;
    }

    /**
     * @param  array{prefix: string, domain: string}  $spec
     * @return list<StaffProfile>
     */
    private function staff(array $spec): array
    {
        $staff = [];
        foreach (range(1, 8) as $n) {
            $female = $n % 2 === 0;
            $staff[] = StaffProfile::create([
                'employee_id' => sprintf('%s-E%03d', $spec['prefix'], $n),
                'first_name' => $this->pick($female ? self::FEMALE : self::MALE),
                'last_name' => $this->pick(self::SURNAMES),
                'gender' => $female ? Gender::Female : Gender::Male,
                'designation' => $n <= 6 ? 'Teacher' : 'Office Staff',
                'qualification' => $n <= 6 ? 'B.Ed' : null,
                'phone' => '98270'.mt_rand(10000, 99999),
                'joined_on' => now()->subYears(mt_rand(1, 8))->toDateString(),
            ]);
        }

        return $staff;
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @param  list<StaffProfile>  $staff
     * @return list<Section>
     */
    private function sections(array $classes, array $staff): array
    {
        $sections = [];
        $i = 0;
        foreach ($classes as $class) {
            foreach (['A', 'B'] as $name) {
                $sections[] = Section::create([
                    'school_class_id' => $class->id, 'name' => $name, 'capacity' => 40, 'sort_order' => $i,
                    'class_teacher_id' => $name === 'A' ? ($staff[$i % 6]->id ?? null) : null,
                ]);
                $i++;
            }
        }

        return $sections;
    }

    /**
     * @param  list<StaffProfile>  $staff
     * @param  list<Section>  $sections
     * @param  list<Subject>  $subjects
     */
    private function assignTeachers(AcademicYear $year, array $staff, array $sections, array $subjects): void
    {
        foreach ($sections as $i => $section) {
            $teacher = $staff[$i % 6];
            TeacherAssignment::create([
                'staff_profile_id' => $teacher->id, 'academic_year_id' => $year->id,
                'school_class_id' => $section->school_class_id, 'section_id' => $section->id,
                'subject_id' => null, 'is_class_teacher' => $section->name === 'A',
            ]);
            // Each section's teachers also teach two subjects there.
            foreach ([$subjects[$i % 3], $subjects[3 + ($i % 3)]] as $subject) {
                TeacherAssignment::create([
                    'staff_profile_id' => $staff[($i + $subject->id) % 6]->id, 'academic_year_id' => $year->id,
                    'school_class_id' => $section->school_class_id, 'section_id' => $section->id, 'subject_id' => $subject->id,
                ]);
            }
        }
    }

    private function holidays(AcademicYear $year): void
    {
        foreach ([
            ['Dussehra', '2026-10-20', '2026-10-20'],
            ['Diwali Break', '2026-11-08', '2026-11-12'],
            ['Christmas', '2026-12-25', '2026-12-25'],
            ['Republic Day', '2027-01-26', '2027-01-26'],
            ['Holi', '2027-03-04', '2027-03-04'],
        ] as [$title, $from, $to]) {
            SchoolHoliday::create(['academic_year_id' => $year->id, 'title' => $title, 'starts_on' => $from, 'ends_on' => $to]);
        }
    }

    /**
     * @param  array{prefix: string, domain: string, name: string}  $spec
     * @param  list<StaffProfile>  $staff
     */
    private function members(array $spec, array $staff): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        foreach ([
            ['admin', 'Principal '.$this->pick(self::SURNAMES), 'school_admin'],
            ['teacher', $staff[0]->fullName(), 'teacher'],
            ['accountant', 'Accounts Office', 'accountant'],
        ] as [$local, $name, $role]) {
            $user = User::create(['name' => $name, 'email' => "{$local}@{$spec['domain']}", 'password' => 'password', 'phone' => '97000'.mt_rand(10000, 99999)]);
            $user->forceFill(['email_verified_at' => now()])->save();

            SchoolMembership::create(['user_id' => $user->id, 'joined_at' => now()])->syncRoles([$roles[$role]]);

            if ($local === 'teacher') {
                $staff[0]->forceFill(['user_id' => $user->id])->save();
            }
        }
    }

    /**
     * @param  array{prefix: string, domain: string}  $spec
     * @param  list<Section>  $sections
     */
    private function students(array $spec, AcademicYear $year, array $sections): void
    {
        $roles = Role::query()->pluck('id', 'slug');
        $serial = 0;
        $firstTwo = [];

        foreach ($sections as $sectionIndex => $section) {
            $classIndex = intdiv($sectionIndex, 2);
            $roll = 0;

            foreach (range(1, mt_rand(6, 9)) as $ignored) {
                $female = mt_rand(0, 1) === 1;
                $serial++;
                $surname = $this->pick(self::SURNAMES);

                $student = Student::create([
                    'admission_no' => sprintf('%s-2026-%03d', $spec['prefix'], $serial),
                    'first_name' => $this->pick($female ? self::FEMALE : self::MALE),
                    'last_name' => $surname,
                    'gender' => $female ? Gender::Female : Gender::Male,
                    'date_of_birth' => now()->setDate(2026, 4, 1)->subYears(3 + $classIndex)->subDays(mt_rand(0, 330))->toDateString(),
                    'admission_date' => now()->setDate(2026, 4, 1)->subYears(min($classIndex, 3))->toDateString(),
                    'address' => mt_rand(1, 99).', '.$this->pick(['Khajrana Road', 'Bhawarkua', 'Vijay Nagar', 'Palasia', 'Scheme 54']).', Indore',
                ]);

                StudentEnrollment::create([
                    'student_id' => $student->id, 'academic_year_id' => $year->id,
                    'school_class_id' => $section->school_class_id, 'section_id' => $section->id,
                    'roll_no' => (string) ++$roll, 'joined_on' => '2026-04-01',
                ]);

                $guardian = Guardian::create([
                    'name' => $this->pick(self::MALE).' '.$surname, 'relation' => GuardianRelation::Father,
                    'phone' => '98'.mt_rand(10000000, 99999999), 'email' => "parent{$serial}@example.test",
                ]);
                StudentGuardian::create([
                    'student_id' => $student->id, 'guardian_id' => $guardian->id, 'relation' => 'father',
                    'is_primary' => true, 'status' => GuardianLinkStatus::Active, 'verified_at' => now(),
                ]);

                if (count($firstTwo) < 2) {
                    $firstTwo[] = [$student, $guardian];
                }
            }
        }

        // The demo parent: one guardian linked to two siblings, with a portal login.
        [[$childOne, $parentGuardian], [$childTwo]] = $firstTwo;
        $parentUser = User::create(['name' => $parentGuardian->name, 'email' => "parent@{$spec['domain']}", 'password' => 'password']);
        $parentUser->forceFill(['email_verified_at' => now()])->save();
        SchoolMembership::create(['user_id' => $parentUser->id, 'joined_at' => now()])->syncRoles([$roles['parent']]);
        $parentGuardian->forceFill(['user_id' => $parentUser->id])->save();
        StudentGuardian::create([
            'student_id' => $childTwo->id, 'guardian_id' => $parentGuardian->id, 'relation' => 'father',
            'status' => GuardianLinkStatus::Active, 'verified_at' => now(),
        ]);
    }

    /** @param  list<string>  $items */
    private function pick(array $items): string
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
