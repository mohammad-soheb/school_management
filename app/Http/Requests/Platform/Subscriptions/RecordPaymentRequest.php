<?php

namespace App\Http\Requests\Platform\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Support\Money\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A verified subscription payment (FR-804, §10.5). Amount in rupees -> integer paise; reference needed for traceable modes. */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.subscriptions.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $traceable = collect(SubscriptionPaymentMode::cases())->filter->needsReference()->map->value->all();

        return [
            'amount' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                $paise = Money::parse((string) $value);
                if ($paise === null || $paise <= 0) {
                    $fail('Enter the amount received in rupees, for example 999 or 2998.');
                } elseif ($paise > 1_000_000_000) {
                    $fail('The amount looks too large.');
                }
            }],
            'paid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.school_today()->toDateString()],
            'mode' => ['required', Rule::enum(SubscriptionPaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:100', Rule::requiredIf(in_array($this->input('mode'), $traceable, true))],
            'months' => ['required', 'integer', 'min:1', 'max:'.(int) config('subscriptions.max_months_per_payment', 36)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['paid_on' => 'payment date', 'mode' => 'payment mode', 'months' => 'months'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference.required' => 'Enter the UTR / transaction or cheque number for this payment mode.',
            'paid_on.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }

    /** @return array<string, mixed> validated fields ready for RenewalService (amount in paise) */
    public function paymentData(): array
    {
        $data = $this->validated();
        $data['amount_paise'] = Money::parse((string) $data['amount']);
        unset($data['amount']);

        return $data;
    }
}
