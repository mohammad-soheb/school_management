<?php

namespace App\Http\Requests\Platform\Subscriptions;

use App\Models\Platform\SubscriptionPlan;
use App\Support\Money\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / edit a subscription plan. The price is typed in rupees and stored as integer paise. */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.subscriptions.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => str($this->input('code'))->lower()->slug()->toString()]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:80'],
            // The code is the stable identifier: required when creating, ignored when editing.
            'code' => $plan ? ['prohibited'] : ['required', 'string', 'max:40', Rule::unique('subscription_plans', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                $paise = Money::parse((string) $value);
                if ($paise === null || $paise <= 0) {
                    $fail('Enter the monthly price in rupees, for example 999 or 1499.50.');
                } elseif ($paise > 100_000_000) {
                    $fail('The monthly price looks too large.');
                }
            }],
            'billing_period_months' => ['required', 'integer', 'min:1', 'max:24'],
            'student_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_hypothesis' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['billing_period_months' => 'billing period', 'monthly_price' => 'monthly price'];
    }

    /** Validated plan fields ready for PlanService (price converted to paise, booleans normalised). @return array<string, mixed> */
    public function planData(): array
    {
        $data = $this->validated();
        $data['monthly_price_paise'] = Money::parse((string) $data['monthly_price']);
        unset($data['monthly_price']);
        $data['is_hypothesis'] = $this->boolean('is_hypothesis');
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }
}
