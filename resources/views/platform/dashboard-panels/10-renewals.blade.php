{{-- Platform dashboard: who needs a renewal call (FR-806). --}}
@can('platform.subscriptions.manage')
    @php
        $renewals = app(\App\Services\Platform\Subscriptions\SubscriptionService::class)->dashboard();
        $lists = [
            ['key' => 'expiring', 'title' => 'Expiring soon', 'empty' => 'No subscription expires in the next '.config('subscriptions.expiring_soon_days').' days.', 'state' => 'expiring_soon'],
            ['key' => 'grace', 'title' => 'In grace period', 'empty' => 'No school is in its grace period.', 'state' => 'grace'],
            ['key' => 'expired', 'title' => 'Expired (read-only)', 'empty' => 'No expired subscriptions.', 'state' => 'expired'],
        ];
    @endphp
    <section aria-labelledby="renewals-title" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="renewals-title" class="text-base font-semibold text-ink">Renewals</h2>
            <div class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                <x-ui.badge tone="success" dot>{{ $renewals['counts']['active'] }} active</x-ui.badge>
                @if ($renewals['counts']['suspended'] > 0)
                    <x-ui.badge tone="neutral" dot>{{ $renewals['counts']['suspended'] }} suspended</x-ui.badge>
                @endif
                @if ($renewals['unmanaged'] > 0)
                    <x-ui.badge tone="info">{{ $renewals['unmanaged'] }} without a plan</x-ui.badge>
                @endif
                <x-ui.button :href="route('platform.subscriptions.index')" variant="ghost" size="sm" icon-right="arrow-right">All renewals</x-ui.button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($lists as $list)
                <x-ui.card :title="$list['title']" :padding="false">
                    <x-slot:actions>
                        <x-ui.button :href="route('platform.subscriptions.index', ['state' => $list['state']])" variant="ghost" size="sm"
                                     :aria-label="'Show all '.$renewals['counts'][$list['state']].' '.strtolower($list['title'])">{{ $renewals['counts'][$list['state']] }}</x-ui.button>
                    </x-slot:actions>
                    @if ($renewals[$list['key']]->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-ink-faint">{{ $list['empty'] }}</p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($renewals[$list['key']] as $subscription)
                                @php $rowStatus = $subscription->status(); @endphp
                                <li class="flex items-center gap-3 px-4 py-3">
                                    <x-ui.avatar :name="$subscription->school->name" size="sm" aria-hidden="true" />
                                    <a href="{{ route('platform.schools.show', $subscription->school) }}" class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-ink hover:underline">{{ $subscription->school->name }}</span>
                                        <span class="block truncate text-xs text-ink-faint">{{ $subscription->plan->name }} · {{ $subscription->expires_on->format('d M Y') }} · {{ $rowStatus->relativeLabel() }}</span>
                                    </a>
                                    <x-ui.button :href="route('platform.subscriptions.schools.payments.create', $subscription->school)" variant="ghost" size="sm" icon="banknotes" class="btn-icon" :aria-label="'Record payment for '.$subscription->school->name" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    </section>
@endcan
