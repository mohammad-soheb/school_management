<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "classes" is avoided as a table name (reserved-word clashes in some tooling); a class here is a grade/standard.
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->tenant();
            $table->string('name', 60);
            $table->string('code', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Where students of this class go on promotion (NULL = final class / graduates).
            $table->tenantForeign('next_class_id', 'school_classes', nullable: true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
