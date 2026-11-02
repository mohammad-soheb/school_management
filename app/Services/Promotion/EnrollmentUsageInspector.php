<?php

namespace App\Services\Promotion;

use App\Models\Students\StudentEnrollment;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Answers "has anything been recorded against this enrollment?" before a committed promotion is reverted or its
 * outcome changed (docs/promotion/ROLLOVER_RULES.md, rule C3).
 *
 * Modules are built independently, so the check is generic: every table in the database with a
 * `student_enrollment_id` (or `enrollment_id`) column is counted for the enrollment, within the active school.
 * A later enrollment that continues from this one (previous_enrollment_id) also counts. Modules whose records point
 * at an enrollment in another way register a check from their provider:
 *
 *   app(EnrollmentUsageInspector::class)->extend('exam marks', fn (StudentEnrollment $e) => Mark::where(...)->count());
 */
class EnrollmentUsageInspector
{
    /** Tables owned by this module: their references are the promotion itself, not usage. */
    private const IGNORED_TABLES = ['promotion_run_items'];

    private const COLUMNS = ['student_enrollment_id', 'enrollment_id'];

    /** @var array<string, Closure(StudentEnrollment): int> */
    private array $checks = [];

    /** @var list<array{table: string, column: string, tenant: bool}>|null */
    private ?array $references = null;

    public function __construct(private readonly TenantContext $context) {}

    /** @param  Closure(StudentEnrollment): int  $check  number of records held against the enrollment */
    public function extend(string $label, Closure $check): void
    {
        $this->checks[$label] = $check;
    }

    /**
     * Records held against the enrollment, keyed by where they were found. Empty = safe to undo.
     *
     * @return array<string, int>
     */
    public function recordsAgainst(StudentEnrollment $enrollment): array
    {
        $schoolId = $this->context->requireId();
        $found = [];

        foreach ($this->references() as $reference) {
            $count = DB::table($reference['table'])
                ->where($reference['column'], $enrollment->getKey())
                ->when($reference['tenant'], fn ($query) => $query->where('school_id', $schoolId))
                ->count();

            if ($count > 0) {
                $found[$reference['table']] = $count;
            }
        }

        $continued = StudentEnrollment::query()->where('previous_enrollment_id', $enrollment->getKey())->count();
        if ($continued > 0) {
            $found['later enrollments'] = $continued;
        }

        foreach ($this->checks as $label => $check) {
            $count = (int) $check($enrollment);
            if ($count > 0) {
                $found[$label] = $count;
            }
        }

        return $found;
    }

    /** @return list<array{table: string, column: string, tenant: bool}> */
    private function references(): array
    {
        if ($this->references !== null) {
            return $this->references;
        }

        $rows = DB::select(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN (?, ?, ?)',
            [...self::COLUMNS, 'school_id'],
        );

        $tenantTables = [];
        $candidates = [];
        foreach ($rows as $row) {
            $table = (string) $row->t;
            $column = (string) $row->c;

            if ($column === 'school_id') {
                $tenantTables[$table] = true;
            } elseif (! in_array($table, self::IGNORED_TABLES, true)) {
                $candidates[] = ['table' => $table, 'column' => $column];
            }
        }

        return $this->references = array_map(
            fn (array $c): array => $c + ['tenant' => isset($tenantTables[$c['table']])],
            $candidates,
        );
    }
}
