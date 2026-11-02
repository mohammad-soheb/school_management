@php $plan ??= null; @endphp
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="name" label="Plan name" :value="$plan?->name" maxlength="80" required />
    @if ($plan)
        <x-form.field label="Code" hint="The code is the plan's stable identifier and cannot be changed.">
            <p class="input flex items-center bg-surface-muted font-mono text-ink-soft">{{ $plan->code }}</p>
        </x-form.field>
    @else
        <x-form.input name="code" label="Code" hint="Lower-case letters, numbers and dashes, e.g. starter. Cannot be changed later." maxlength="40" required />
    @endif
    <x-form.money name="monthly_price" label="Monthly price" :paise="$plan?->monthly_price_paise" hint="Before GST / taxes." required />
    <x-form.input name="billing_period_months" label="Billing period (months)" type="number" min="1" max="24" inputmode="numeric"
                  :value="$plan?->billing_period_months ?? 1" hint="Default number of months a payment renews." required />
    <x-form.input name="student_limit" label="Student limit" type="number" min="1" inputmode="numeric" :value="$plan?->student_limit"
                  hint="Leave empty for no limit. Shown to the school; not enforced yet." />
    <x-form.textarea name="description" label="What is included" :value="$plan?->description" rows="3" class="sm:col-span-2" />
    <x-form.toggle name="is_hypothesis" label="Price is a hypothesis" hint="Not a validated final offer (D10)." :checked="$plan?->is_hypothesis ?? true" />
    <x-form.toggle name="is_active" label="Available for new subscriptions" hint="Retired plans stay on existing subscriptions." :checked="$plan?->is_active ?? true" />
</div>
