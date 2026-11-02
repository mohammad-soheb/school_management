<?php

namespace App\Http\Requests\SchoolSetup;

use App\Services\SchoolSetup\SchoolSettingsService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setup.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $prefix = $this->input('fees.receipt_prefix');

        if (is_string($prefix)) {
            $fees = (array) $this->input('fees', []);
            $fees['receipt_prefix'] = strtoupper(trim($prefix));
            $this->merge(['fees' => $fees]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'attendance' => ['required', 'array'],
            'attendance.leave_counts_as' => ['required', Rule::in(array_keys(SchoolSettingsService::LEAVE_TREATMENTS))],
            'attendance.late_counts_as_present' => ['nullable', 'boolean'],
            'attendance.half_day_weight' => ['required', 'numeric', 'min:0', 'max:1', 'decimal:0,2'],
            'attendance.weekly_off' => ['nullable', 'array', 'max:6'],
            'attendance.weekly_off.*' => ['integer', 'distinct', 'between:0,6'],

            'fees' => ['required', 'array'],
            'fees.receipt_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9][A-Z0-9\-]*$/'],
            'fees.receipt_scope' => ['required', Rule::in(array_keys(SchoolSettingsService::RECEIPT_SCOPES))],
            'fees.receipt_padding' => ['required', 'integer', 'between:3,10'],
            'fees.allocation_order' => ['required', Rule::in(array_keys(SchoolSettingsService::ALLOCATION_ORDERS))],

            'security' => ['nullable', 'array'],
            'security.require_two_factor_for' => ['nullable', 'array'],
            'security.require_two_factor_for.*' => ['string', 'distinct', 'not_in:parent', TenantRule::exists('roles', 'slug')],

            'features' => ['nullable', 'array'],
            'features.salary' => ['nullable', 'boolean'],
            'features.certificates' => ['nullable', 'boolean'],
            'features.id_cards' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (count((array) $this->input('attendance.weekly_off', [])) >= 7) {
                    $validator->errors()->add('attendance.weekly_off', 'At least one day of the week must be a working day.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fees.receipt_prefix.regex' => 'Use capital letters, digits and hyphens only (for example RCP or SPS-R).',
            'attendance.weekly_off.max' => 'At least one day of the week must be a working day.',
            'attendance.half_day_weight.max' => 'A half day can count for at most one full day.',
            'security.require_two_factor_for.*.exists' => 'Choose roles of this school.',
            'security.require_two_factor_for.*.not_in' => 'Parents cannot be required to use two-factor authentication here.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'attendance.leave_counts_as' => 'leave treatment',
            'attendance.half_day_weight' => 'half-day weight',
            'fees.receipt_prefix' => 'receipt prefix',
            'fees.receipt_padding' => 'receipt number digits',
            'fees.receipt_scope' => 'receipt numbering',
            'fees.allocation_order' => 'allocation order',
        ];
    }
}
