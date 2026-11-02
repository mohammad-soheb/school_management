<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per student on a submitted sheet. The status attaches to the enrollment, so it keeps its original
        // academic year after promotion (FR-2B04). date/session are copied from the sheet for fast per-student reports.
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('attendance_sheet_id', 'attendance_sheets');
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('student_id', 'students');
            $table->date('date');
            $table->string('session', 10)->default('day');
            // present | absent | late | leave | half_day
            $table->string('status', 20);
            $table->string('remark', 255)->nullable();
            // Last correction (FR-307); the full history is in attendance_corrections.
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'attendance_sheet_id', 'student_enrollment_id'], 'uq_student_attendance_sheet_enrollment');
            // One status per student, date and session even if a student changes section during the day.
            $table->unique(['school_id', 'student_id', 'date', 'session'], 'uq_student_attendance_student_day');
            $table->index(['school_id', 'date', 'status'], 'idx_student_attendance_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance');
    }
};
