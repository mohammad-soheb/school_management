<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->string('name', 20);
            $table->unsignedSmallInteger('capacity')->nullable();
            // The section's class teacher (shown on report cards, defaults for attendance).
            $table->tenantForeign('class_teacher_id', 'staff_profiles', nullable: true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'school_class_id', 'name']);
        });

        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years', nullable: true);
            $table->string('title', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            // holiday | event | exam
            $table->string('type', 20)->default('holiday');
            $table->timestamps();

            $table->index(['school_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holidays');
        Schema::dropIfExists('sections');
    }
};
