<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One class register per section, date and session (FR-301, FR-304). V1 uses a single "day" session.
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->tenantForeign('section_id', 'sections');
            $table->date('date');
            $table->string('session', 10)->default('day');
            // submitted (see App\Enums\Attendance\SheetStatus)
            $table->string('status', 20)->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // The duplicate guard: a second submit of the same register can never create a second sheet.
            $table->unique(['school_id', 'section_id', 'date', 'session'], 'uq_attendance_sheets_section_day');
            $table->index(['school_id', 'date'], 'idx_attendance_sheets_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
