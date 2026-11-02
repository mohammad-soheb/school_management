<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily staff attendance (FR-302): one row per staff member per date.
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('staff_profile_id', 'staff_profiles');
            $table->date('date');
            // present | absent | late | leave | half_day
            $table->string('status', 20);
            $table->string('remark', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'staff_profile_id', 'date'], 'uq_staff_attendance_staff_day');
            $table->index(['school_id', 'date'], 'idx_staff_attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');
    }
};
