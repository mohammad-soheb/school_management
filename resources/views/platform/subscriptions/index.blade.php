<x-layouts.platform title="Renewals">
    <x-ui.page-header title="Renewals" description="Every school subscription, its expiry and its last payment. Payments are verified outside the ERP and recorded here.">
        <x-slot:actions>
            <x-ui.button :href="route('platform.subscriptions.plans.index')" variant="secondary" icon="rectangle-stack">Plans</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $tiles = [
            'active' => ['icon' => 'check-badge', 'tone' => 'success'],
            'expiring_soon' => ['icon' => 'clock', 'tone' => 'warning'],
            'grace' => ['icon' => 'exclamation-triangle', 'tone' => 'warning'],
            'expired' => ['icon' => 'x-circle', 'tone' => 'danger'],
            'suspended' => ['icon' => 'pause-circle', 'tone' => 'neutral'],
        ];
    @endphp
    <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($tiles as $value => $tile)
            <x-ui.stat :label="$stateOptions[$value]" :value="number_format($counts[$value] ?? 0)" :icon="$tile['icon']" :tone="$tile['tone']"
                       :href="route('platform.subscriptions.index', ['state' => $value])" />
        @endforeach
    </div>

    <x-ui.filters :reset="route('platform.subscriptions.index')" placeholder="Search school name, code or city" class="mt-6">
        <x-form.select name="state" label="Status" placeholder="All statuses" :options="$stateOptions" :selected="request('state')" />
        <x-form.select name="plan_id" label="Plan" placeholder="All plans" :options="$planOptions" :selected="request('plan_id')" />
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($subscriptions->isEmpty())
            <x-ui.empty icon="arrow-path" title="No subscriptions found"
                        description="Assign a plan from a school's page, or change the filters." >
                <x-ui.button :href="route('platform.schools.index')" variant="secondary" icon="building-library">Open schools</x-ui.button>
            </x-ui.empty>
        @else
            <x-ui.table caption="School subscriptions">
                <x-slot:head>
                    <x-ui.th column="school" label="School" />
                    <x-ui.th>Plan</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th column="expires_on" label="Expires" />
                    <x-ui.th>Last payment</x-ui.th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>
                @foreach ($subscriptions as $subscription)
                    @php $status = $subscription->status(); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('platform.schools.show', $subscription->school) }}" class="flex items-center gap-3">
                                <x-ui.avatar :name="$subscription->school->name" size="sm" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-ink hover:underline">{{ $subscription->school->name }}</span>
                                    <span class="block truncate font-mono text-xs text-ink-faint">{{ $subscription->school->code }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="whitespace-nowrap">
                            {{ $subscription->plan->name }}
                            <span class="block text-xs text-ink-faint">{{ \App\Support\Money\Money::format($subscription->plan->monthly_price_paise) }}/month</span>
                        </td>
                        <td><x-ui.badge :tone="$status->state->tone()" dot>{{ $status->state->label() }}</x-ui.badge></td>
                        <td class="whitespace-nowrap">
                            {{ $subscription->expires_on->format('d M Y') }}
                            <span class="block text-xs text-ink-faint">{{ $status->relativeLabel() }}</span>
                        </td>
                        <td class="whitespace-nowrap text-ink-soft">
                            @if ($subscription->latestPayment)
                                {{ \App\Support\Money\Money::format($subscription->latestPayment->amount_paise) }}
                                <span class="block text-xs text-ink-faint">{{ $subscription->latestPayment->paid_on->format('d M Y') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="col-actions">
                            <div class="flex justify-end gap-1">
                                <x-ui.button :href="route('platform.subscriptions.schools.payments.create', $subscription->school)" variant="ghost" size="sm" icon="banknotes">Record payment</x-ui.button>
                                <x-ui.button :href="route('platform.subscriptions.schools.show', $subscription->school)" variant="ghost" size="sm">History</x-ui.button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
        @if ($subscriptions->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $subscriptions->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.platform>
