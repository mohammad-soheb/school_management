<x-layouts.platform :title="'Subscription · '.$school->name">
    <x-ui.page-header :title="$school->name.' · subscription'" :description="$subscription->plan->name.' plan · expires '.$subscription->expires_on->format('d M Y')"
                      :breadcrumbs="[['label' => 'Renewals', 'url' => route('platform.subscriptions.index')], ['label' => $school->name, 'url' => route('platform.schools.show', $school)], ['label' => 'Subscription history']]">
        <x-ui.badge :tone="$status->state->tone()" dot>{{ $status->state->label() }}</x-ui.badge>
        <x-slot:actions>
            <x-ui.button :href="route('platform.subscriptions.schools.edit', $school)" variant="secondary" icon="pencil-square">Change plan or dates</x-ui.button>
            <x-ui.button :href="route('platform.subscriptions.schools.payments.create', $school)" icon="banknotes">Record payment</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Payments" :padding="false" class="lg:col-span-2">
            @include('platform.subscriptions._payments')
        </x-ui.card>
        <x-ui.card title="Renewal history" :padding="false">
            @include('platform.subscriptions._history')
        </x-ui.card>
    </div>
</x-layouts.platform>
