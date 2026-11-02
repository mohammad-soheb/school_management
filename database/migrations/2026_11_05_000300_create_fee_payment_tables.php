<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments, receipt numbering, reversals and allocations (FR-405, FR-406, FR-409, FR-411, FR-413, FR-415).
 * The UNIQUE keys below are the last line of defence behind the row locks in the services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            // '2026-27' (academic-year scope) or 'all' (never restarts).
            $table->string('scope_key', 20);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            // The row locked FOR UPDATE while a receipt number is taken.
            $table->unique(['school_id', 'scope_key'], 'uq_fee_receipt_sequences_scope');
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->string('receipt_no', 40);
            $table->unsignedBigInteger('receipt_number');
            $table->unsignedBigInteger('amount_paise');
            // cash | upi | bank_transfer | cheque | other
            $table->string('mode', 20);
            $table->string('reference', 80)->nullable();
            $table->date('instrument_date')->nullable();
            $table->date('paid_on');
            $table->foreignId('received_by')->constrained('users');
            $table->string('remarks', 500)->nullable();
            // posted | reversed
            $table->string('status', 20)->default('posted');
            $table->string('idempotency_key', 64);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'receipt_no'], 'uq_fee_payments_receipt');
            $table->unique(['school_id', 'idempotency_key'], 'uq_fee_payments_key');
            $table->index(['school_id', 'paid_on'], 'idx_fee_payments_paid_on');
            $table->index(['school_id', 'student_id'], 'idx_fee_payments_student');
        });

        Schema::create('fee_payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('fee_payment_id', 'fee_payments');
            $table->unsignedBigInteger('amount_paise');
            $table->string('reason', 500);
            $table->date('reversed_on');
            $table->foreignId('reversed_by')->constrained('users');
            $table->timestamps();

            // One reversal per payment: a double click on "Reverse" can never write two.
            $table->unique(['school_id', 'fee_payment_id'], 'uq_fee_payment_reversals_payment');
            $table->index(['school_id', 'reversed_on'], 'idx_fee_payment_reversals_on');
        });

        Schema::create('fee_allocations', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('fee_payment_id', 'fee_payments');
            $table->tenantForeign('fee_charge_line_id', 'fee_charge_lines');
            $table->tenantForeign('student_id', 'students');
            // Signed: positive settles the line, negative (kind = reversal) undoes an earlier allocation.
            $table->bigInteger('amount_paise');
            // payment | credit | reversal
            $table->string('kind', 20);
            $table->date('allocated_on');
            $table->tenantForeign('fee_payment_reversal_id', 'fee_payment_reversals', nullable: true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'fee_charge_line_id'], 'idx_fee_allocations_line');
            $table->index(['school_id', 'fee_payment_id'], 'idx_fee_allocations_payment');
            $table->index(['school_id', 'student_id'], 'idx_fee_allocations_student');
        });

        DB::statement('ALTER TABLE fee_payments ADD CONSTRAINT chk_fee_payments_amount CHECK (amount_paise > 0)');
        DB::statement("ALTER TABLE fee_allocations ADD CONSTRAINT chk_fee_allocations_sign CHECK ((kind = 'reversal' AND amount_paise < 0) OR (kind <> 'reversal' AND amount_paise > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_allocations');
        Schema::dropIfExists('fee_payment_reversals');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_receipt_sequences');
    }
};
