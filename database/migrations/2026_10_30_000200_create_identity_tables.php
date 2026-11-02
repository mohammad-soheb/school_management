<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 60);
            $table->string('slug', 60);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'slug']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->tenantForeign('role_id', 'roles');
            $table->string('permission', 100);
            $table->timestamps();

            $table->unique(['role_id', 'permission']);
        });

        Schema::create('school_memberships', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('membership_role', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id');
            $table->foreign('school_id', 'fk_membership_role_school')->references('id')->on('schools')->restrictOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->primary(['membership_id', 'role_id']);
            $table->foreign(['school_id', 'membership_id'], 'fk_membership_role_membership')
                ->references(['school_id', 'id'])->on('school_memberships')->cascadeOnDelete();
            $table->foreign(['school_id', 'role_id'], 'fk_membership_role_role')
                ->references(['school_id', 'id'])->on('roles')->restrictOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->json('role_ids');
            // Set when the invitation is for a parent/guardian login; the Students module adds the FK.
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('membership_role');
        Schema::dropIfExists('school_memberships');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
