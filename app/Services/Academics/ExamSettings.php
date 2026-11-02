<?php

namespace App\Services\Academics;

use App\Enums\Academics\ReportCardTemplate;
use App\Models\Academics\ExamSetting;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;

/**
 * Effective exam & report card settings for the active school:
 *   config/school_defaults/exams.php  <  $school->settings['exams']  <  exam_settings row (Exams > Settings).
 */
class ExamSettings
{
    /** Keys a school may change on the settings screen. */
    public const EDITABLE = [
        'percentage_decimals', 'absent_counts_in_total', 'rank_enabled', 'rank_scope', 'default_max_marks', 'default_pass_marks',
        'report_card_template', 'show_grade_points', 'show_rank_on_report_card', 'signature_labels', 'pass_remark', 'fail_remark', 'footer_note',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        $school = $this->context->requireSchool();

        $values = array_replace(
            (array) config('school_defaults.exams', []),
            (array) Arr::get($school->settings ?? [], 'exams', []),
            (array) (ExamSetting::query()->first()?->values ?? []),
        );

        $values['percentage_decimals'] = max(0, min(2, (int) ($values['percentage_decimals'] ?? 2)));
        $values['signature_labels'] = array_values(array_filter((array) ($values['signature_labels'] ?? []), fn ($l) => filled($l)));
        $values['report_card_template'] = ReportCardTemplate::tryFrom((string) ($values['report_card_template'] ?? ''))?->value ?? ReportCardTemplate::Classic->value;

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Options that change the arithmetic, stored with every publication so a snapshot can be reproduced.
     *
     * @return array{percentage_decimals: int, absent_counts_in_total: bool, rank_enabled: bool, rank_scope: string}
     */
    public function calculationOptions(): array
    {
        $all = $this->all();

        return [
            'percentage_decimals' => (int) $all['percentage_decimals'],
            'absent_counts_in_total' => (bool) ($all['absent_counts_in_total'] ?? true),
            'rank_enabled' => (bool) ($all['rank_enabled'] ?? true),
            'rank_scope' => ($all['rank_scope'] ?? 'class') === 'section' ? 'section' : 'class',
        ];
    }

    /** @param  array<string, mixed>  $values  validated */
    public function update(array $values, User $actor): void
    {
        $before = $this->all();
        $values = Arr::only($values, self::EDITABLE);

        $row = ExamSetting::query()->first() ?? new ExamSetting(['values' => []]);
        $row->fill(['values' => array_replace($row->values ?? [], $values), 'updated_by' => $actor->getKey()])->save();

        [$old, $new] = $this->audit->diff(Arr::only($before, self::EDITABLE), Arr::only($this->all(), self::EDITABLE));
        if ($new !== []) {
            $this->audit->record('exam_settings.updated', $row, $old, $new, actor: $actor);
        }
    }
}
