<x-layouts.platform title="New plan">
    <x-ui.page-header title="New plan"
                      :breadcrumbs="[['label' => 'Renewals', 'url' => route('platform.subscriptions.index')], ['label' => 'Plans', 'url' => route('platform.subscriptions.plans.index')], ['label' => 'New plan']]" />

    <x-form.form :action="route('platform.subscriptions.plans.store')" class="space-y-6">
        <x-ui.card title="Plan details">
            @include('platform.subscriptions.plans._form')
        </x-ui.card>
        <x-form.actions sticky>
            <x-ui.button :href="route('platform.subscriptions.plans.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Create plan</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.platform>
