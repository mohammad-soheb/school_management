<x-layouts.platform :title="'Edit subscription · '.$school->name">
    <x-ui.page-header title="Change plan or dates" :description="'Corrections and plan changes for '.$school->name.'. To renew after a payment, use Record payment instead.'"
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => $school->name, 'url' => route('platform.schools.show', $school)], ['label' => 'Edit subscription']]">
        <x-slot:actions>
            <x-ui.button :href="route('platform.subscriptions.schools.payments.create', $school)" variant="secondary" icon="banknotes">Record payment</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-form.form :action="route('platform.subscriptions.schools.update', $school)" method="PUT" class="space-y-6">
        <x-ui.card title="Subscription">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="subscription_plan_id" label="Plan" :options="$planOptions" :selected="$subscription->subscription_plan_id" required class="sm:col-span-2"
                               hint="A plan change applies to payments recorded from now on." />
                <x-form.date name="starts_on" label="Starts on" :value="$subscription->starts_on->toDateString()" required />
                <x-form.date name="expires_on" label="Expires on" :value="$subscription->expires_on->toDateString()" hint="Last day of access." required />
                <x-form.input name="grace_days" label="Grace days" type="number" min="0" :max="config('subscriptions.max_grace_days')" inputmode="numeric"
                              :value="$subscription->grace_days" required />
                <x-form.textarea name="notes" label="Internal notes" :value="$subscription->notes" rows="3" class="sm:col-span-2" />
                <x-form.input name="reason" label="Reason for the change" class="sm:col-span-2" maxlength="500"
                              hint="Recorded in the renewal history and the audit log." required />
            </div>
        </x-ui.card>
        <x-form.actions sticky>
            <x-ui.button :href="route('platform.schools.show', $school)" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save changes</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.platform>
