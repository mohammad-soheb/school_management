<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Homework / assignments (FR-505, FR-508). Attachments live in stored_files (purpose "homework",
        // attachable = this row). Rows are archived, never deleted.
        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('academic_year_id', 'academic_years');
            $table->tenantForeign('school_class_id', 'school_classes');
            // NULL section = set for the whole class (every section).
            $table->tenantForeign('section_id', 'sections', nullable: true);
            $table->tenantForeign('subject_id', 'subjects');
            $table->string('title', 150);
            $table->text('description');
            $table->date('assigned_on');
            $table->date('due_on');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'school_class_id', 'due_on'], 'idx_homework_class_due');
            $table->index(['school_id', 'status', 'due_on'], 'idx_homework_status_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework');
    }
};
