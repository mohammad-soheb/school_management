<?php

namespace App\Providers\Modules;

use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\ClassSubject;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Policies\SchoolSetup\AcademicYearPolicy;
use App\Policies\SchoolSetup\SchoolClassPolicy;
use App\Policies\SchoolSetup\SchoolHolidayPolicy;
use App\Policies\SchoolSetup\SectionPolicy;
use App\Policies\SchoolSetup\SubjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * School setup module (master plan FR-201, FR-202). The setup models are core (shared) models, so their policies are
 * registered here instead of with #[UsePolicy].
 */
class SchoolSetupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AcademicYear::class, AcademicYearPolicy::class);
        Gate::policy(SchoolClass::class, SchoolClassPolicy::class);
        Gate::policy(Section::class, SectionPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(SchoolHoliday::class, SchoolHolidayPolicy::class);

        // Classes a subject is taught in (for "in use" checks and list counts). Subject is a core model.
        Subject::resolveRelationUsing('classSubjects', fn (Subject $subject) => $subject->hasMany(ClassSubject::class));
    }
}
