<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which staff member teaches which class/section/subject in an academic year.
        // Teachers are restricted to these rows for attendance, marks and homework (master plan FR-303, FR-503).
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('staff_profile_id', 'staff_profiles');
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('school_class_id', 'school_classes');
            // NULL section = the whole class; NULL subject = class-teacher style assignment.
            $table->tenantForeign('section_id', 'sections', nullable: true);
            $table->tenantForeign('subject_id', 'subjects', nullable: true);
            $table->boolean('is_class_teacher')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'staff_profile_id'], 'idx_assignments_staff_year');
            $table->index(['school_id', 'academic_year_id', 'section_id'], 'idx_assignments_section_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
