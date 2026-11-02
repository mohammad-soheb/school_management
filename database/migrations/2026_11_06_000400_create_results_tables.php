<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every publish of an exam's results for a class (history: who, when, why). Unpublishing withdraws it.
        Schema::create('result_publications', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->unsignedSmallInteger('version');
            // active | withdrawn
            $table->string('status', 20)->default('active');
            // 1 while active, NULL once withdrawn: at most one active publication per exam + class.
            $table->unsignedTinyInteger('active_flag')->nullable();
            $table->unsignedInteger('students_count')->default(0);
            $table->string('reason', 500)->nullable();
            // Calculation options used (rounding, absent rule, rank) so the snapshot is reproducible.
            $table->json('options')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdraw_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'school_class_id', 'version'], 'uq_result_publications_version');
            $table->unique(['exam_id', 'school_class_id', 'active_flag'], 'uq_result_publications_active');
        });

        // Immutable per-student result snapshot written when results are published. Never updated or deleted:
        // a correction is a new publication with new rows.
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('result_publication_id', 'result_publications');
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->tenantForeign('section_id', 'sections', nullable: true);
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('student_id', 'students');
            // Copied at publish time so the printed card never changes when a name or roll number is edited later.
            $table->string('student_name', 170);
            $table->string('admission_no', 30);
            $table->string('roll_no', 10)->nullable();
            $table->decimal('total_obtained', 8, 2);
            $table->decimal('total_max', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->string('grade_remark', 100)->nullable();
            $table->boolean('passed');
            $table->unsignedSmallInteger('rank')->nullable();
            // Per subject: name, code, max, pass, obtained, status, grade, passed, components.
            $table->json('subjects');
            $table->string('teacher_remark', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['result_publication_id', 'student_enrollment_id'], 'uq_exam_results_publication_enrollment');
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'exam_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('result_publications');
    }
};
