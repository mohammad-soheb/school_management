<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posted charges (FR-403, FR-411), opening balances (FR-416) and adjustments (FR-404).
 * Charges, lines and opening balances are insert-only (models refuse updates and deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_charge_batches', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('fee_plan_id', 'fee_plans', nullable: true);
            $table->date('due_until');
            $table->string('idempotency_key', 64);
            $table->unsignedInteger('students_count')->default(0);
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedBigInteger('total_paise')->default(0);
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();

            // A double click / retry of the same posting form returns the same batch.
            $table->unique(['school_id', 'idempotency_key'], 'uq_fee_charge_batches_key');
        });

        Schema::create('fee_charges', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('fee_charge_batch_id', 'fee_charge_batches', nullable: true);
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('academic_year_id', 'academic_years');
            // plan | opening_balance
            $table->string('source', 20);
            $table->unsignedBigInteger('total_paise');
            $table->date('posted_on');
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'student_id'], 'idx_fee_charges_student');
        });

        Schema::create('fee_charge_lines', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('fee_charge_id', 'fee_charges');
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('fee_head_id', 'fee_heads', nullable: true);
            $table->tenantForeign('fee_plan_line_id', 'fee_plan_lines', nullable: true);
            $table->unsignedTinyInteger('installment_no')->nullable();
            $table->string('description', 150);
            $table->unsignedBigInteger('amount_paise');
            $table->date('due_on');
            $table->timestamps();

            // Posting idempotency: one line per student, plan line and instalment, enforced by the database.
            $table->unique(['school_id', 'student_id', 'fee_plan_line_id', 'installment_no'], 'uq_fee_charge_lines_installment');
            $table->index(['school_id', 'due_on'], 'idx_fee_charge_lines_due');
            $table->index(['school_id', 'student_id', 'due_on'], 'idx_fee_charge_lines_student_due');
        });

        Schema::create('fee_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->unsignedBigInteger('amount_paise');
            // manual | import | carry_forward
            $table->string('source', 20);
            $table->string('source_reference', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->date('due_on');
            $table->tenantForeign('carried_from_enrollment_id', 'student_enrollments', nullable: true);
            // Null when nothing was carried (amount 0): the row still records that the carry-forward ran.
            $table->tenantForeign('fee_charge_id', 'fee_charges', nullable: true);
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Carry-forward idempotency: one row per (from, to) enrollment pair.
            $table->unique(['school_id', 'carried_from_enrollment_id', 'student_enrollment_id'], 'uq_fee_opening_balances_carry');
            $table->unique(['school_id', 'idempotency_key'], 'uq_fee_opening_balances_key');
            $table->index(['school_id', 'student_id'], 'idx_fee_opening_balances_student');
        });

        Schema::create('fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('fee_charge_line_id', 'fee_charge_lines');
            // discount | scholarship | waiver | fine | carry_forward
            $table->string('type', 20);
            $table->unsignedBigInteger('amount_paise');
            // Signed effect on the line's due: +amount for a fine, -amount for every reduction.
            $table->bigInteger('effect_paise');
            $table->string('reason', 500);
            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->tenantForeign('fee_opening_balance_id', 'fee_opening_balances', nullable: true);
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'idempotency_key'], 'uq_fee_adjustments_key');
            $table->index(['school_id', 'status'], 'idx_fee_adjustments_status');
            $table->index(['school_id', 'fee_charge_line_id', 'status'], 'idx_fee_adjustments_line');
        });

        // Defence in depth for the money invariants (MySQL 8 enforces CHECK constraints).
        DB::statement('ALTER TABLE fee_charge_lines ADD CONSTRAINT chk_fee_charge_lines_amount CHECK (amount_paise > 0)');
        DB::statement('ALTER TABLE fee_adjustments ADD CONSTRAINT chk_fee_adjustments_amount CHECK (amount_paise > 0 AND (effect_paise = amount_paise OR effect_paise = -amount_paise))');
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_adjustments');
        Schema::dropIfExists('fee_opening_balances');
        Schema::dropIfExists('fee_charge_lines');
        Schema::dropIfExists('fee_charges');
        Schema::dropIfExists('fee_charge_batches');
    }
};
