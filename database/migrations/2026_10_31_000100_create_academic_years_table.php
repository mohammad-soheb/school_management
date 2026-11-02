<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            // 1 when current, NULL otherwise: a unique index on it allows at most one current year per school.
            $table->unsignedTinyInteger('current_marker')->nullable()->virtualAs('IF(is_current, 1, NULL)');
            // open | locked. A locked year is read-only until an authorised user reopens it.
            $table->string('status', 20)->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'current_marker'], 'uq_academic_years_one_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
