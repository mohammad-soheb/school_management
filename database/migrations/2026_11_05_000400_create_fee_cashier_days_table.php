<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily cashier close (FR-407, FR-414, D14). One row per school per business date. Every payment and reversal locks
 * its day row FOR UPDATE, so a close and a payment for the same day are serialised (design §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_cashier_days', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->date('business_date');
            // open | closed
            $table->string('status', 20)->default('open');

            // Snapshot written at close (net of reversals dated that day, by mode).
            $table->bigInteger('cash_paise')->default(0);
            $table->bigInteger('upi_paise')->default(0);
            $table->bigInteger('bank_transfer_paise')->default(0);
            $table->bigInteger('cheque_paise')->default(0);
            $table->bigInteger('other_paise')->default(0);
            $table->unsignedBigInteger('gross_paise')->default(0);
            $table->unsignedBigInteger('reversed_paise')->default(0);
            $table->bigInteger('net_paise')->default(0);
            $table->unsignedInteger('receipts_count')->default(0);
            $table->unsignedInteger('reversals_count')->default(0);
            $table->bigInteger('counted_cash_paise')->nullable();
            $table->bigInteger('difference_paise')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->json('breakdown')->nullable();

            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'business_date'], 'uq_fee_cashier_days_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_cashier_days');
    }
};
