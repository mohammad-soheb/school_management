<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee setup (FR-401, FR-402): fee heads and per-year, per-class fee plans with dated instalment lines.
 * Design: docs/fees/FEE_LEDGER_DESIGN.md §4-§5. All money is integer paise (FR-412).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 80);
            $table->string('code', 20);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'uq_fee_heads_code');
            $table->unique(['school_id', 'name'], 'uq_fee_heads_name');
        });

        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('school_class_id', 'school_classes');
            $table->string('name', 120);
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // One fee structure per class per year.
            $table->unique(['school_id', 'academic_year_id', 'school_class_id'], 'uq_fee_plans_year_class');
        });

        Schema::create('fee_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('fee_plan_id', 'fee_plans');
            $table->tenantForeign('fee_head_id', 'fee_heads');
            // Amount of ONE instalment.
            $table->unsignedBigInteger('amount_paise');
            // one_time | monthly | quarterly | half_yearly | yearly (App\Enums\Fees\FeeFrequency)
            $table->string('frequency', 20);
            $table->unsignedTinyInteger('installments')->default(1);
            $table->date('first_due_on');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'fee_plan_id', 'fee_head_id'], 'uq_fee_plan_lines_head');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plan_lines');
        Schema::dropIfExists('fee_plans');
        Schema::dropIfExists('fee_heads');
    }
};
