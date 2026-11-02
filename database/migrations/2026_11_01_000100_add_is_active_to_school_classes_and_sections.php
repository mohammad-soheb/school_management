<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School setup (FR-202): a class or section that already has enrollments, assignments or other history cannot be
 * deleted, so it can be deactivated instead. Inactive classes/sections stay readable for history but are hidden from
 * pickers for new records (ClassStructureService::classOptions()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('next_class_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
