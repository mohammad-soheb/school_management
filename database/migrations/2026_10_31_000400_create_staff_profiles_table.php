<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            // Login account, if the staff member has one (teachers/accountants usually do).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_id', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('qualification', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            // active | inactive | left
            $table->string('status', 20)->default('active');
            // Points at stored_files.id (no FK; see schools.logo_file_id).
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'employee_id']);
            $table->unique(['school_id', 'user_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
