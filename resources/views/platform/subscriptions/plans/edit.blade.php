<x-layouts.platform :title="'Edit '.$plan->name">
    <x-ui.page-header :title="'Edit '.$plan->name" description="Price changes apply to payments recorded from now on. Past payments keep the price they were recorded with."
                      :breadcrumbs="[['label' => 'Renewals', 'url' => route('platform.subscriptions.index')], ['label' => 'Plans', 'url' => route('platform.subscriptions.plans.index')], ['label' => $plan->name]]" />

    <x-form.form :action="route('platform.subscriptions.plans.update', $plan)" method="PUT" class="space-y-6">
        <x-ui.card title="Plan details">
            @include('platform.subscriptions.plans._form', ['plan' => $plan])
        </x-ui.card>
        <x-form.actions sticky>
            <x-ui.button :href="route('platform.subscriptions.plans.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save plan</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.platform>
