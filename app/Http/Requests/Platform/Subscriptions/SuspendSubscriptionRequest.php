<?php

namespace App\Http\Requests\Platform\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class SuspendSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.subscriptions.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['suspension_reason' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['suspension_reason' => 'reason'];
    }
}
