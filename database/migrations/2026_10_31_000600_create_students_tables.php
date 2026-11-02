<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permanent student identity. Class/section/roll number live on student_enrollments, per academic year.
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('admission_no', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('blood_group', 5)->nullable();
            $table->date('admission_date')->nullable();
            // active | inactive | transferred | withdrawn | graduated
            $table->string('status', 20)->default('active');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('previous_school', 150)->nullable();
            // Points at stored_files.id (no FK; see schools.logo_file_id).
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'admission_no']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->tenantForeign('section_id', 'sections', nullable: true);
            $table->string('roll_no', 10)->nullable();
            // active | promoted | retained | transferred | withdrawn | graduated | completed
            $table->string('status', 20)->default('active');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('exit_reason')->nullable();
            // The enrollment this one continues from (previous academic year).
            $table->tenantForeign('previous_enrollment_id', 'student_enrollments', nullable: true);
            // Set by the year-end promotion workflow; FK added by that module.
            $table->unsignedBigInteger('promotion_run_id')->nullable();
            $table->timestamps();

            // One enrollment per student per academic year: duplicate active enrollments are impossible.
            $table->unique(['school_id', 'student_id', 'academic_year_id'], 'uq_enrollment_student_year');
            $table->index(['school_id', 'academic_year_id', 'school_class_id', 'section_id'], 'idx_enrollment_year_class_section');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            // Parent portal login, once the guardian accepts an invitation.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('relation', 30)->default('guardian');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
            $table->index(['school_id', 'phone']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreign(['school_id', 'guardian_id'], 'fk_invitations_guardian')
                ->references(['school_id', 'id'])->on('guardians')->restrictOnDelete();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('guardian_id', 'guardians');
            $table->string('relation', 30);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            // active | revoked. The parent portal only honours active links that were verified by the school.
            $table->string('status', 20)->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign('fk_invitations_guardian');
        });
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
    }
};
