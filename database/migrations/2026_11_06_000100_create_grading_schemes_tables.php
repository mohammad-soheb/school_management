<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A school's grading scale (percentage bands -> grade). Exams reference one scheme.
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            // 1 for the school's default scheme, NULL otherwise: the unique key allows only one default per school.
            $table->unsignedTinyInteger('default_flag')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'default_flag'], 'uq_grading_schemes_default');
        });

        // Bands cover 0..100 without gaps or overlaps: min inclusive, max exclusive (the top band includes 100).
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('grading_scheme_id', 'grading_schemes');
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('grade', 10);
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->string('remark', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['grading_scheme_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('grading_schemes');
    }
};
