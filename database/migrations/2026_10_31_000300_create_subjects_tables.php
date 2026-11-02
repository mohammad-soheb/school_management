<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            // scholastic | co_scholastic
            $table->string('type', 20)->default('scholastic');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->tenantForeign('subject_id', 'subjects');
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
    }
};
