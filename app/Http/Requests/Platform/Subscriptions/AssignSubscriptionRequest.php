<?php

namespace App\Http\Requests\Platform\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Assign a first plan to a school (FR-803). expires_on is optional: it defaults to one billing period. */
class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.subscriptions.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2099-12-31'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2099-12-31'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:'.(int) config('subscriptions.max_grace_days', 90)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['subscription_plan_id' => 'plan', 'starts_on' => 'start date', 'expires_on' => 'expiry date'];
    }
}
