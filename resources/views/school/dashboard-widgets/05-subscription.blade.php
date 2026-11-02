{{-- School dashboard: ERP subscription warning (FR-806). Only for users who may see the subscription (billing.view),
     and only when it needs attention (expiring soon, grace, expired, suspended). Receives $user, $school. --}}
@can('billing.view')
    @php
        $erpSubscription = app(\App\Services\Platform\Subscriptions\SubscriptionService::class)->forActiveSchool();
        $erpStatus = $erpSubscription?->status();
    @endphp
    @if ($erpStatus !== null && $erpStatus->state->needsAttention())
        @php
            $state = $erpStatus->state;
            $contact = config('subscriptions.renewal_contact');
            [$tone, $title, $text] = match ($state) {
                \App\Enums\Subscriptions\SubscriptionState::ExpiringSoon => ['warning', 'ERP subscription renewal due',
                    $erpStatus->relativeLabel().' ('.$erpSubscription->expires_on->format('d M Y').'). Renew with '.$contact.' to avoid interruption.'],
                \App\Enums\Subscriptions\SubscriptionState::Grace => ['warning', 'ERP subscription expired: grace period',
                    'Everything keeps working until '.$erpStatus->graceEndsOn->format('d M Y').'; after that the app becomes read-only. Renew with '.$contact.'.'],
                \App\Enums\Subscriptions\SubscriptionState::Expired => ['danger', 'ERP subscription expired: read-only mode',
                    'You can view and export records, but nothing can be added or changed until renewal. No data has been deleted. Renew with '.$contact.'.'],
                default => ['danger', 'ERP access paused', 'Your ERP provider has paused this school\'s access. No data has been deleted. Contact '.$contact.'.'],
            };
        @endphp
        <x-ui.alert :tone="$tone" :title="$title">
            <p>{{ $text }}</p>
            <p class="mt-2"><a href="{{ route('school.subscription.show') }}" class="font-medium underline">View subscription details</a></p>
        </x-ui.alert>
    @endif
@endcan
