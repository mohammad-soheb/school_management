<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year-end promotion / rollover (master plan Phase 2B, FR-2B01..07; docs/promotion/ROLLOVER_RULES.md).
 *
 * A run maps the active enrollments of chosen classes in a source year to outcomes for a target year. It is
 * prepared as a draft, then committed once in a single transaction. Items are the per-student decisions and stay
 * the single place where that decision is corrected later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_runs', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('from_academic_year_id', 'academic_years');
            $table->tenantForeign('to_academic_year_id', 'academic_years');
            // draft | committed | cancelled
            $table->string('status', 20)->default('draft');
            // {"class_ids": [..], "carry_forward_dues": bool, "lock_source_year": bool}
            $table->json('options')->nullable();
            // Retry protection for "start a run" (the commit itself is protected by the run row lock + status).
            $table->string('idempotency_key', 64);
            // {"promoted": n, "retained": n, "graduated": n, "transferred": n, "withdrawn": n, "skipped": n, "dues_carried_paise": n}
            $table->json('counts')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'idempotency_key'], 'uq_promotion_runs_idempotency');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'from_academic_year_id', 'to_academic_year_id'], 'idx_promotion_runs_years');
        });

        Schema::create('promotion_run_items', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('promotion_run_id', 'promotion_runs');
            // The source-year enrollment being closed.
            $table->tenantForeign('student_enrollment_id', 'student_enrollments');
            $table->tenantForeign('student_id', 'students');
            $table->tenantForeign('from_class_id', 'school_classes');
            // promote | retain | graduate | transfer | withdraw
            $table->string('outcome', 20);
            $table->tenantForeign('target_class_id', 'school_classes', nullable: true);
            $table->tenantForeign('target_section_id', 'sections', nullable: true);
            $table->string('target_roll_no', 10)->nullable();
            $table->string('note', 255)->nullable();
            // pending | applied | skipped | reverted
            $table->string('state', 20)->default('pending');
            // Why the item was skipped (student_inactive | already_enrolled | source_changed).
            $table->string('skip_reason', 40)->nullable();
            // The target-year enrollment created (or reused) for this student on commit.
            $table->tenantForeign('target_enrollment_id', 'student_enrollments', nullable: true);
            // What students.status was before commit, so a revert can restore it.
            $table->string('previous_student_status', 20)->nullable();
            $table->unsignedBigInteger('dues_carried_paise')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'promotion_run_id', 'student_enrollment_id'], 'uq_promotion_items_run_enrollment');
            $table->index(['school_id', 'promotion_run_id', 'from_class_id'], 'idx_promotion_items_run_class');
            $table->index(['school_id', 'student_id']);
        });

        // student_enrollments.promotion_run_id exists in the core table without a FK; this module owns it.
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreign(['school_id', 'promotion_run_id'], 'fk_student_enrollments_promotion_run')
                ->references(['school_id', 'id'])->on('promotion_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropForeign('fk_student_enrollments_promotion_run');
            // MySQL created a supporting index with the constraint's name.
            $table->dropIndex('fk_student_enrollments_promotion_run');
        });
        Schema::dropIfExists('promotion_run_items');
        Schema::dropIfExists('promotion_runs');
    }
};
