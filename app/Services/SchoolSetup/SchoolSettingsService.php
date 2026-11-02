<?php

namespace App\Services\SchoolSetup;

use App\Models\Identity\Role;
use App\Models\Platform\School;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Per-school overrides of config/school_defaults/* (FR-201 "basic settings"), stored in schools.settings JSON.
 *
 * Only the keys listed in EDITABLE are written, each one is merged into the existing JSON (other modules' keys, such as
 * exams.*, are never wiped), and every change is audited as "school.settings_changed" with a key-by-key diff.
 * Values are validated by UpdateSchoolSettingsRequest; this class only casts them to their stored type.
 */
class SchoolSettingsService
{
    /** Setting key => stored type. */
    public const EDITABLE = [
        'attendance.leave_counts_as' => 'string',
        'attendance.late_counts_as_present' => 'bool',
        'attendance.half_day_weight' => 'float',
        'attendance.weekly_off' => 'int_list',
        'fees.receipt_prefix' => 'string',
        'fees.receipt_scope' => 'string',
        'fees.receipt_padding' => 'int',
        'fees.allocation_order' => 'string',
        'security.require_two_factor_for' => 'string_list',
        'features.salary' => 'bool',
        'features.certificates' => 'bool',
        'features.id_cards' => 'bool',
    ];

    public const LEAVE_TREATMENTS = ['excluded' => 'Not counted (left out of the percentage)', 'absent' => 'Counted as absent', 'present' => 'Counted as present'];

    public const RECEIPT_SCOPES = ['academic_year' => 'Restart every academic year (RCP/2026-27/000123)', 'never' => 'Never restart (RCP/000123)'];

    public const ALLOCATION_ORDERS = ['oldest_due_first' => 'Oldest due first', 'selected_only' => 'Only the charges the cashier selects'];

    public const WEEKDAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    public const FEATURES = [
        'salary' => ['Salary', 'Staff salary sheets and payslips.'],
        'certificates' => ['Certificates', 'Transfer / leaving and other certificates.'],
        'id_cards' => ['ID cards', 'Printable student ID cards.'],
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Effective value of every editable setting (override, else default), keyed by dot path.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $school = $this->context->requireSchool();
        $values = [];
        foreach (self::EDITABLE as $key => $type) {
            $values[$key] = $this->cast($school->setting($key), $type);
        }

        return $values;
    }

    /**
     * Staff role slugs that may be required to use two-factor authentication (parents use the portal).
     *
     * @return array<string, string> slug => name
     */
    public function roleOptions(): array
    {
        return Role::query()->where('slug', '!=', 'parent')->orderBy('name')->pluck('name', 'slug')->all();
    }

    /**
     * @param  array<string, mixed>  $data  validated input, nested (attendance => [...], fees => [...], ...)
     * @return array<string, mixed> the keys that changed (new values)
     */
    public function update(array $data, User $actor): array
    {
        $school = $this->context->requireSchool();

        return DB::transaction(function () use ($school, $data, $actor): array {
            School::query()->whereKey($school->getKey())->lockForUpdate()->first(['id']);
            $school->refresh();

            $before = $after = [];
            $settings = $school->settings ?? [];

            foreach (self::EDITABLE as $key => $type) {
                $before[$key] = $this->cast($school->setting($key), $type);

                if (! Arr::has($data, $key) && ! in_array($type, ['bool', 'int_list', 'string_list'], true)) {
                    $after[$key] = $before[$key];

                    continue;
                }

                // Unticked checkboxes and empty multi-selects submit nothing: they mean false / none.
                $value = $this->cast(Arr::get($data, $key), $type);
                Arr::set($settings, $key, $value);
                $after[$key] = $value;
            }

            [$old, $new] = $this->audit->diff($before, $after);
            if ($new === []) {
                return [];
            }

            $school->settings = $settings;
            $school->save();

            $this->audit->record('school.settings_changed', $school, $old, $new, actor: $actor);

            return $new;
        });
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'float' => round((float) $value, 2),
            'int_list' => $this->intList($value),
            'string_list' => array_values(array_unique(array_map('strval', array_filter((array) ($value ?? []), fn ($v) => $v !== null && $v !== '')))),
            default => $value === null ? null : (string) $value,
        };
    }

    /** @return list<int> */
    private function intList(mixed $value): array
    {
        $list = array_values(array_unique(array_map('intval', array_filter((array) ($value ?? []), fn ($v) => $v !== null && $v !== ''))));
        sort($list);

        return $list;
    }
}
