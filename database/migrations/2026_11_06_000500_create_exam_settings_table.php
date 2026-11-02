<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-school overrides of config/school_defaults/exams.php (rounding, rank, report card template ...).
        Schema::create('exam_settings', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->json('values');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('school_id', 'uq_exam_settings_school');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_settings');
    }
};
