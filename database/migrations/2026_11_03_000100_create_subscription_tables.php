<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-owned subscription tables (FR-803 .. FR-809). They are NOT tenant tables: only the Super Admin
 * backoffice writes them, and school-facing reads filter by the active school explicitly. Each row still carries
 * school_id (plain FK to schools) so a school's history can never be attached to another school.
 * Money is integer paise. Payments and renewal events are append-only (never updated or deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_paise');
            $table->unsignedTinyInteger('billing_period_months')->default(1);
            $table->unsignedInteger('student_limit')->nullable();
            // Master plan §20.1 / D10: prices are hypotheses until validated with schools.
            $table->boolean('is_hypothesis')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // One current subscription per school; its history lives in renewal_events and subscription_payments.
        Schema::create('school_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained('schools')->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('expires_on')->index();
            $table->unsignedSmallInteger('grace_days');
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            // Last state seen by the daily refresh / a write, so status transitions can be logged once.
            $table->string('last_status', 20)->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('school_subscription_id')->constrained('school_subscriptions')->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->unsignedBigInteger('amount_paise');
            // Plan price x months at the time of recording, so short / discounted payments stay visible.
            $table->unsignedBigInteger('expected_paise');
            $table->date('paid_on');
            $table->string('mode', 20);
            $table->string('reference', 100)->nullable();
            $table->unsignedSmallInteger('months');
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->text('notes')->nullable();
            // Platform-wide unique: a double click / retry re-sends the same key and gets the first payment back.
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'paid_on']);
        });

        Schema::create('renewal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('school_subscription_id')->constrained('school_subscriptions')->restrictOnDelete();
            $table->foreignId('subscription_payment_id')->nullable()->constrained('subscription_payments')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('previous_plan_id')->nullable()->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('new_plan_id')->nullable()->constrained('subscription_plans')->restrictOnDelete();
            $table->date('previous_expires_on')->nullable();
            $table->date('new_expires_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_events');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('school_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
