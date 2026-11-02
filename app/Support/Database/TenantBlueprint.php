<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * Migration helpers that enforce tenant isolation in the database itself (plan.md §2.6, level 6).
 *
 *   $table->id();
 *   $table->tenant();                                  // school_id + FK to schools + UNIQUE (school_id, id)
 *   $table->tenantForeign('student_id', 'students');   // student_id + composite FK (school_id, student_id) -> students (school_id, id)
 *
 * With composite foreign keys a row physically cannot reference a row that belongs to another school.
 * For nullable references MySQL skips the check when the column is NULL, which is the behaviour we want.
 */
final class TenantBlueprint
{
    public static function register(): void
    {
        Blueprint::macro('tenant', function (): void {
            /** @var Blueprint $this */
            $table = $this->getTable();

            $this->unsignedBigInteger('school_id');
            $this->foreign('school_id', substr("fk_{$table}_school", 0, 64))->references('id')->on('schools')->restrictOnDelete();
            $this->unique(['school_id', 'id'], substr("uq_{$table}_school_id", 0, 64));
        });

        Blueprint::macro('tenantForeign', function (string $column, string $references, bool $nullable = false): ColumnDefinition {
            /** @var Blueprint $this */
            $definition = $this->unsignedBigInteger($column);

            if ($nullable) {
                $definition->nullable();
            }

            $this->foreign(['school_id', $column], substr("fk_{$this->getTable()}_{$column}", 0, 64))
                ->references(['school_id', 'id'])
                ->on($references)
                ->restrictOnDelete();

            return $definition;
        });
    }
}
