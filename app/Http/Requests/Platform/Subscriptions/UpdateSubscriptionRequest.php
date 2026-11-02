<?php

namespace App\Http\Requests\Platform\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Change a subscription's plan, dates or grace period (FR-803). A reason is recorded in the history and audit log. */
class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.subscriptions.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Retired plans are accepted only when unchanged (checked in SubscriptionService::updateTerms()).
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2099-12-31'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2099-12-31'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:'.(int) config('subscriptions.max_grace_days', 90)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['subscription_plan_id' => 'plan', 'starts_on' => 'start date', 'expires_on' => 'expiry date', 'reason' => 'reason for the change'];
    }
}
