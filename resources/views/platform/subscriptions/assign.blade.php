<x-layouts.platform :title="'Assign plan · '.$school->name">
    <x-ui.page-header title="Assign a plan" :description="$school->name.' has no subscription yet. Assigning one activates its access and starts its renewal history.'"
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => $school->name, 'url' => route('platform.schools.show', $school)], ['label' => 'Assign plan']]" />

    @if ($planOptions === [])
        <x-ui.card>
            <x-ui.empty icon="rectangle-stack" title="No active plans" description="Create a plan, or load the hypothesis plans, before assigning one.">
                <x-ui.button :href="route('platform.subscriptions.plans.index')" icon="rectangle-stack">Open plans</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-form.form :action="route('platform.subscriptions.schools.store', $school)" class="space-y-6">
            <x-ui.card title="Subscription">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select name="subscription_plan_id" label="Plan" :options="$planOptions" placeholder="Choose a plan" required class="sm:col-span-2" />
                    <x-form.date name="starts_on" label="Starts on" :value="school_today()->toDateString()" required />
                    <x-form.date name="expires_on" label="Expires on" hint="Last day of access. Leave empty for one billing period of the plan." />
                    <x-form.input name="grace_days" label="Grace days" type="number" min="0" :max="config('subscriptions.max_grace_days')" inputmode="numeric"
                                  :value="config('subscriptions.default_grace_days')" hint="Full access continues this many days after expiry, with a warning." required />
                    <x-form.textarea name="notes" label="Internal notes" rows="3" class="sm:col-span-2" hint="Visible to the platform team only." />
                </div>
            </x-ui.card>
            <x-form.actions sticky>
                <x-ui.button :href="route('platform.schools.show', $school)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="play-circle">Assign plan</x-ui.button>
            </x-form.actions>
        </x-form.form>
    @endif
</x-layouts.platform>
