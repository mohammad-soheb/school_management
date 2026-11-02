<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history of student status changes (withdraw, transfer, inactive, reactivate) - FR-207.
        // The student row only holds the CURRENT status; this table keeps every change with its date and reason.
        Schema::create('student_status_changes', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            // The enrollment that was closed or reopened by the change (NULL when the student had none this year).
            $table->tenantForeign('student_enrollment_id', 'student_enrollments', nullable: true);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            // School-local date the change takes effect (left school on / returned on).
            $table->date('effective_on');
            $table->string('reason', 500);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Profile "history" tab lists a student's changes newest first.
            $table->index(['school_id', 'student_id', 'effective_on'], 'idx_status_changes_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_status_changes');
    }
};
