<x-layouts.platform :title="'Record payment · '.$school->name">
    <x-ui.page-header title="Record a verified payment" :description="'Verify the payment first (bank statement, UPI app, cash in hand). Recording it renews '.$school->name.' immediately.'"
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => $school->name, 'url' => route('platform.schools.show', $school)], ['label' => 'Record payment']]">
        <x-ui.badge :tone="$status->state->tone()" dot>{{ $status->state->label() }}</x-ui.badge>
    </x-ui.page-header>

    @if ($subscription->isSuspended())
        <x-ui.alert tone="warning" title="This subscription is suspended" class="mb-6">
            Recording a payment extends the expiry date but does not lift the suspension. Resume it from the school's page when ready.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-form.form :action="route('platform.subscriptions.schools.payments.store', $school)" class="space-y-6 lg:col-span-2">
            <x-form.idempotency />
            <x-ui.card title="Payment">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.money name="amount" label="Amount received" :paise="$subscription->plan->priceForMonths($months)" required
                                  :hint="'Plan price: '.\App\Support\Money\Money::format($subscription->plan->monthly_price_paise).' per month.'" />
                    <x-form.date name="paid_on" label="Payment date" :value="school_today()->toDateString()" :max="school_today()->toDateString()" required />
                    <x-form.select name="mode" label="Mode" :options="$modeOptions" placeholder="Choose a mode" required />
                    <x-form.input name="reference" label="Reference" maxlength="100" hint="UTR / transaction id / cheque number. Required for UPI, bank transfer and cheque." />
                    <x-form.input name="months" label="Months to add" type="number" min="1" :max="config('subscriptions.max_months_per_payment')" inputmode="numeric"
                                  :value="$months" required />
                    <x-form.textarea name="notes" label="Notes" rows="2" class="sm:col-span-2" />
                </div>
            </x-ui.card>
            <x-form.actions sticky>
                <x-ui.button :href="route('platform.schools.show', $school)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-circle">Record payment and renew</x-ui.button>
            </x-form.actions>
        </x-form.form>

        <x-ui.card title="Renewal preview" description="The new period starts from the later of today and the current expiry, so no paid days are lost.">
            <x-ui.dl>
                <x-ui.dl-item label="Plan">{{ $subscription->plan->name }}</x-ui.dl-item>
                <x-ui.dl-item label="Current expiry">{{ $subscription->expires_on->format('d M Y') }} <span class="text-ink-faint">({{ $status->relativeLabel() }})</span></x-ui.dl-item>
                <x-ui.dl-item :label="'With '.$months.' '.\Illuminate\Support\Str::plural('month', $months)">
                    {{ $preview->startsOn->format('d M Y') }} – <span class="font-medium text-ink">{{ $preview->endsOn->format('d M Y') }}</span>
                </x-ui.dl-item>
            </x-ui.dl>
            <p class="mt-4 text-xs text-ink-faint">The preview assumes the default number of months. Subscription payments are kept apart from the school's student-fee ledger.</p>
        </x-ui.card>
    </div>
</x-layouts.platform>
