<x-layouts.platform title="Plans">
    <x-ui.page-header title="Subscription plans" description="What schools pay for the ERP. Plans are retired, never deleted, because payments refer to them."
                      :breadcrumbs="[['label' => 'Renewals', 'url' => route('platform.subscriptions.index')], ['label' => 'Plans']]">
        <x-slot:actions>
            <x-form.form :action="route('platform.subscriptions.plans.hypotheses')">
                <x-ui.button type="submit" variant="secondary" icon="sparkles">Load hypothesis plans</x-ui.button>
            </x-form.form>
            <x-ui.button :href="route('platform.subscriptions.plans.create')" icon="plus">New plan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert tone="info" class="mb-6">
        Prices marked <strong>Hypothesis</strong> come from the master plan §20.1 pricing hypotheses (decision D10). They are not final offers;
        GST/taxes and annual discounts are not applied.
    </x-ui.alert>

    <x-ui.card :padding="false">
        @if ($plans->isEmpty())
            <x-ui.empty icon="rectangle-stack" title="No plans yet" description="Create a plan, or load the Starter / Standard / Growth hypothesis plans.">
                <x-ui.button :href="route('platform.subscriptions.plans.create')" icon="plus">New plan</x-ui.button>
            </x-ui.empty>
        @else
            <x-ui.table caption="Subscription plans">
                <x-slot:head>
                    <x-ui.th>Plan</x-ui.th>
                    <x-ui.th class="num">Monthly price</x-ui.th>
                    <x-ui.th>Billing period</x-ui.th>
                    <x-ui.th class="num">Student limit</x-ui.th>
                    <x-ui.th class="num">Schools</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>
                @foreach ($plans as $plan)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $plan->name }}</span>
                            @if ($plan->is_hypothesis)<x-ui.badge tone="info" class="ml-1">Hypothesis</x-ui.badge>@endif
                            <span class="block font-mono text-xs text-ink-faint">{{ $plan->code }}</span>
                        </td>
                        <td class="num font-medium text-ink">{{ \App\Support\Money\Money::format($plan->monthly_price_paise) }}</td>
                        <td class="text-ink-soft">
                            {{ $plan->billing_period_months }} {{ \Illuminate\Support\Str::plural('month', $plan->billing_period_months) }}
                            @if ($plan->billing_period_months > 1)
                                <span class="block text-xs text-ink-faint">{{ \App\Support\Money\Money::format($plan->periodPricePaise()) }} per period</span>
                            @endif
                        </td>
                        <td class="num text-ink-soft">{{ $plan->student_limit ? number_format($plan->student_limit) : 'No limit' }}</td>
                        <td class="num text-ink-soft">{{ number_format($plan->subscriptions_count) }}</td>
                        <td><x-ui.badge :tone="$plan->is_active ? 'success' : 'neutral'" dot>{{ $plan->is_active ? 'Active' : 'Retired' }}</x-ui.badge></td>
                        <td class="col-actions">
                            <x-ui.button :href="route('platform.subscriptions.plans.edit', $plan)" variant="ghost" size="sm" icon="pencil-square">Edit</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-layouts.platform>
