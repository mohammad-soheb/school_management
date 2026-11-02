<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('grading_scheme_id', 'grading_schemes');
            $table->string('name', 120);
            $table->string('term', 60)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            // draft | marks_open | published | locked (App\Enums\Academics\ExamStatus)
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->timestamp('marks_opened_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'name']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'starts_on']);
        });

        // Classes the exam applies to.
        Schema::create('exam_classes', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->timestamps();

            $table->unique(['exam_id', 'school_class_id']);
        });

        // A subject examined in one class of the exam, with its maximum and pass marks.
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_id', 'exams');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->tenantForeign('subject_id', 'subjects');
            // When the subject has components, max_marks is the sum of the components' max marks.
            $table->decimal('max_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            // false = graded on its own but left out of the overall total / percentage / pass.
            $table->boolean('counts_in_total')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'school_class_id', 'subject_id'], 'uq_exam_subjects_exam_class_subject');
            $table->index(['school_id', 'exam_id', 'school_class_id'], 'idx_exam_subjects_exam_class');
        });

        // Optional parts of an exam subject with their own max marks (theory, practical ...).
        Schema::create('exam_components', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('exam_subject_id', 'exam_subjects');
            $table->string('name', 60);
            $table->decimal('max_marks', 6, 2);
            // NULL = no separate pass mark for this component.
            $table->decimal('pass_marks', 6, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_subject_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_components');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_classes');
        Schema::dropIfExists('exams');
    }
};
