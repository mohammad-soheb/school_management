<x-layouts.app title="ERP subscription">
    <x-ui.page-header title="ERP subscription" description="The plan this school pays for the ERP. This is separate from your students' fees.">
        @if ($status)
            <x-ui.badge :tone="$status->state->tone()" dot>{{ $status->state->label() }}</x-ui.badge>
        @endif
    </x-ui.page-header>

    @if ($subscription === null)
        <x-ui.card>
            <x-ui.empty icon="credit-card" title="No subscription on record"
                        description="Your school has full access. Your ERP provider has not set up a subscription plan for it yet." />
        </x-ui.card>
    @else
        @php
            $state = $status->state;
            $contact = config('subscriptions.renewal_contact');
        @endphp

        @switch($state)
            @case(\App\Enums\Subscriptions\SubscriptionState::Suspended)
                <x-ui.alert tone="danger" title="Access is paused" class="mb-6">
                    Your ERP provider has paused this school's access. No data has been deleted. Contact {{ $contact }} to restore access.
                </x-ui.alert>
                @break
            @case(\App\Enums\Subscriptions\SubscriptionState::Expired)
                <x-ui.alert tone="danger" title="Subscription expired: the app is read-only" class="mb-6">
                    You can still open and export every record, but nothing can be added or changed until the subscription is renewed.
                    No data has been deleted. Renew with {{ $contact }}.
                </x-ui.alert>
                @break
            @case(\App\Enums\Subscriptions\SubscriptionState::Grace)
                <x-ui.alert tone="warning" title="Subscription expired: grace period" class="mb-6">
                    Everything keeps working until {{ $status->graceEndsOn->format('d M Y') }}. After that the app becomes read-only. Renew with {{ $contact }}.
                </x-ui.alert>
                @break
            @case(\App\Enums\Subscriptions\SubscriptionState::ExpiringSoon)
                <x-ui.alert tone="warning" title="Renewal due" class="mb-6">
                    {{ $status->relativeLabel() }} ({{ $subscription->expires_on->format('d M Y') }}). Renew with {{ $contact }} to avoid interruption.
                </x-ui.alert>
                @break
        @endswitch

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="Current plan" class="lg:col-span-2">
                <x-ui.dl grid>
                    <x-ui.dl-item label="Plan">{{ $subscription->plan->name }}</x-ui.dl-item>
                    <x-ui.dl-item label="Monthly price">{{ \App\Support\Money\Money::format($subscription->plan->monthly_price_paise) }} <span class="text-ink-faint">+ taxes as applicable</span></x-ui.dl-item>
                    <x-ui.dl-item label="Student limit">{{ $subscription->plan->student_limit ? number_format($subscription->plan->student_limit) : 'No limit' }}</x-ui.dl-item>
                    <x-ui.dl-item label="Valid from">{{ $subscription->starts_on->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item label="Valid until">{{ $subscription->expires_on->format('d M Y') }} <span class="text-ink-faint">({{ $status->relativeLabel() }})</span></x-ui.dl-item>
                    <x-ui.dl-item label="Grace period">{{ $subscription->grace_days }} {{ \Illuminate\Support\Str::plural('day', $subscription->grace_days) }} after expiry</x-ui.dl-item>
                </x-ui.dl>
                @if ($subscription->plan->description)
                    <p class="mt-5 text-sm text-ink-soft">{{ $subscription->plan->description }}</p>
                @endif
            </x-ui.card>

            <x-ui.card title="History" :padding="false">
                @include('platform.subscriptions._history', ['showActor' => false])
            </x-ui.card>
        </div>

        <x-ui.card title="Payments received" :padding="false" class="mt-6">
            @include('platform.subscriptions._payments', ['showRecorder' => false])
        </x-ui.card>
    @endif
</x-layouts.app>
