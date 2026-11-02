<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One section x one exam subject: the unit a teacher saves (draft) and submits.
        Schema::create('mark_sheets', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('exam_subject_id', 'exam_subjects');
            $table->tenantForeign('section_id', 'sections');
            // draft | submitted
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'section_id']);
            $table->index(['school_id', 'exam_id']);
        });

        // A student's mark in one exam subject. Never deleted: clearing a cell sets the status back to pending.
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('exam_subject_id', 'exam_subjects');
            $table->tenantForeign('mark_sheet_id', 'mark_sheets');
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('student_id', 'students');
            // pending | present | absent | not_applicable | exempt
            $table->string('status', 20)->default('pending');
            // Total for the subject (sum of components when it has components). NULL unless present.
            $table->decimal('marks_obtained', 6, 2)->nullable();
            // {"<exam_component_id>": "12.50", ...} for subjects with components.
            $table->json('component_marks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_enrollment_id'], 'uq_marks_subject_enrollment');
            $table->index(['school_id', 'exam_id']);
        });

        // Class teacher's remark for a student in an exam (printed on the report card).
        Schema::create('exam_remarks', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->string('remark', 500)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_remarks');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('mark_sheets');
    }
};
