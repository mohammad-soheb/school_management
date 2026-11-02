<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 30)->default('private');
            // Random, school-prefixed storage key; never derived from the uploaded file name.
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->string('extension', 12);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->string('purpose', 50);
            $table->string('attachable_type', 120)->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'purpose']);
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
