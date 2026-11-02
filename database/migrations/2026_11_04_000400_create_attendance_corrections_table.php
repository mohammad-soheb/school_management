<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history of changes to submitted attendance (FR-304, FR-307): who changed what, when and why.
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            // student | staff, and the student_attendance / staff_attendance id
            $table->string('record_type', 20);
            $table->unsignedBigInteger('record_id');
            $table->tenantForeign('student_id', 'students', nullable: true);
            $table->tenantForeign('staff_profile_id', 'staff_profiles', nullable: true);
            $table->date('date');
            $table->string('old_status', 20);
            $table->string('new_status', 20);
            $table->string('old_remark', 255)->nullable();
            $table->string('new_remark', 255)->nullable();
            $table->string('reason', 500);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'record_type', 'record_id'], 'idx_attendance_corrections_record');
            $table->index(['school_id', 'date'], 'idx_attendance_corrections_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
