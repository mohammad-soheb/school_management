<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notices / circulars (FR-506, FR-508). A notice shows while status = published, publish_at <= now and
        // (expires_at is NULL or in the future). Attachments live in stored_files (purpose "notice").
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status', 'publish_at'], 'idx_notices_status_publish');
        });

        // Who a notice is for (one row per target). See App\Enums\Communication\NoticeAudienceType.
        Schema::create('notice_audiences', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('notice_id', 'notices');
            $table->string('type', 20);
            $table->tenantForeign('school_class_id', 'school_classes', nullable: true);
            $table->tenantForeign('role_id', 'roles', nullable: true);
            $table->timestamps();

            $table->index(['school_id', 'type', 'school_class_id'], 'idx_notice_aud_class');
            $table->index(['school_id', 'type', 'role_id'], 'idx_notice_aud_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_audiences');
        Schema::dropIfExists('notices');
    }
};
