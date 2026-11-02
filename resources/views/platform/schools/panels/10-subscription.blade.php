{{-- Platform school page panel: this school's ERP subscription (FR-803 .. FR-805). Receives $school. --}}
@can('platform.subscriptions.manage')
    @php
        $subscriptionService = app(\App\Services\Platform\Subscriptions\SubscriptionService::class);
        $subscription = $subscriptionService->forSchool($school);
        $subscriptionStatus = $subscription?->status();
        $recent = $subscription ? $subscriptionService->history($subscription, limit: 5) : null;
    @endphp

    @if ($subscription === null)
        <x-ui.card title="ERP subscription">
            <x-ui.empty icon="credit-card" title="No subscription"
                        description="This school is unmanaged: it has full access until a plan is assigned. Assign a plan to start tracking expiry and renewals.">
                <x-ui.button :href="route('platform.subscriptions.schools.create', $school)" icon="play-circle">Assign plan</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="ERP subscription" class="lg:col-span-2">
                <x-slot:actions>
                    <x-ui.dropdown>
                        <x-slot:trigger>
                            <button type="button" class="btn btn-secondary btn-sm">More</button>
                        </x-slot:trigger>
                        <x-ui.dropdown-link :href="route('platform.subscriptions.schools.edit', $school)" icon="pencil-square">Change plan or dates</x-ui.dropdown-link>
                        <x-ui.dropdown-link :href="route('platform.subscriptions.schools.show', $school)" icon="clock">Full history</x-ui.dropdown-link>
                        @unless ($subscription->isSuspended())
                            <x-ui.dropdown-link as="button" type="button" danger icon="pause-circle" @click="$dispatch('open-modal', 'suspend-subscription')">Suspend subscription</x-ui.dropdown-link>
                        @endunless
                    </x-ui.dropdown>
                    <x-ui.button :href="route('platform.subscriptions.schools.payments.create', $school)" size="sm" icon="banknotes">Record payment</x-ui.button>
                </x-slot:actions>

                <div class="mb-5 flex flex-wrap items-center gap-3">
                    <x-ui.badge :tone="$subscriptionStatus->state->tone()" dot>{{ $subscriptionStatus->state->label() }}</x-ui.badge>
                    <span class="text-sm text-ink-soft">{{ $subscriptionStatus->relativeLabel() }}</span>
                    @if ($subscriptionStatus->state === \App\Enums\Subscriptions\SubscriptionState::Grace)
                        <span class="text-sm text-warning-ink">Grace ends {{ $subscriptionStatus->graceEndsOn->format('d M Y') }}</span>
                    @endif
                </div>

                @if ($subscription->isSuspended())
                    <x-ui.alert tone="danger" title="Suspended" class="mb-5">
                        <p>{{ $subscription->suspension_reason }}</p>
                        <p class="mt-1 text-xs">Since {{ $subscription->suspended_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}{{ $subscription->suspendedBy ? ' by '.$subscription->suspendedBy->name : '' }}. Users of the school see only the subscription notice.</p>
                        <x-form.form :action="route('platform.subscriptions.schools.resume', $school)" class="mt-3">
                            <x-ui.button type="submit" variant="success" size="sm" icon="play-circle">Resume subscription</x-ui.button>
                        </x-form.form>
                    </x-ui.alert>
                @endif

                <x-ui.dl grid>
                    <x-ui.dl-item label="Plan">
                        {{ $subscription->plan->name }}
                        <span class="block text-xs text-ink-faint">{{ \App\Support\Money\Money::format($subscription->plan->monthly_price_paise) }}/month{{ $subscription->plan->student_limit ? ' · up to '.number_format($subscription->plan->student_limit).' students' : '' }}</span>
                    </x-ui.dl-item>
                    <x-ui.dl-item label="Started">{{ $subscription->starts_on->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item label="Expires (last day)">{{ $subscription->expires_on->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item label="Grace period">{{ $subscription->grace_days }} {{ \Illuminate\Support\Str::plural('day', $subscription->grace_days) }} · until {{ $subscriptionStatus->graceEndsOn->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item label="Last payment">
                        @if ($subscription->latestPayment)
                            {{ \App\Support\Money\Money::format($subscription->latestPayment->amount_paise) }} · {{ $subscription->latestPayment->mode->label() }}
                            <span class="block text-xs text-ink-faint">{{ $subscription->latestPayment->paid_on->format('d M Y') }}{{ $subscription->latestPayment->reference ? ' · '.$subscription->latestPayment->reference : '' }}</span>
                        @else
                            —
                        @endif
                    </x-ui.dl-item>
                    @if ($subscription->notes)
                        <x-ui.dl-item label="Notes">{{ $subscription->notes }}</x-ui.dl-item>
                    @endif
                </x-ui.dl>
            </x-ui.card>

            <x-ui.card title="Recent history" :padding="false">
                <x-slot:actions>
                    <x-ui.button :href="route('platform.subscriptions.schools.show', $school)" variant="ghost" size="sm" icon-right="arrow-right">All</x-ui.button>
                </x-slot:actions>
                @include('platform.subscriptions._history', ['events' => $recent['events']])
            </x-ui.card>
        </div>

        @unless ($subscription->isSuspended())
            <x-ui.modal name="suspend-subscription" title="Suspend this subscription?"
                        description="School users will see only a 'subscription paused' notice until you resume it. No data is deleted, and the school can still sign in."
                        :show="$errors->has('suspension_reason')">
                <x-form.form :action="route('platform.subscriptions.schools.suspend', $school)" id="suspend-subscription-form">
                    <x-form.textarea name="suspension_reason" label="Reason (shown in the history and audit log)" rows="3" required />
                </x-form.form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'suspend-subscription')">Cancel</x-ui.button>
                    <x-ui.button type="submit" form="suspend-subscription-form" variant="danger">Suspend subscription</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endunless
    @endif
@endcan
