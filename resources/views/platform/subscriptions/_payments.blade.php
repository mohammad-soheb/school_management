{{-- Subscription payments table. Receives $payments (with plan; recordedBy optional). --}}
@php $showRecorder = $showRecorder ?? true; @endphp
@if ($payments->isEmpty())
    <x-ui.empty icon="banknotes" title="No payments recorded" description="Verified subscription payments will be listed here." />
@else
    <x-ui.table caption="Subscription payments" dense>
        <x-slot:head>
            <x-ui.th>Paid on</x-ui.th>
            <x-ui.th class="num">Amount</x-ui.th>
            <x-ui.th>Mode</x-ui.th>
            <x-ui.th>Reference</x-ui.th>
            <x-ui.th>Period covered</x-ui.th>
            @if ($showRecorder)<x-ui.th>Recorded by</x-ui.th>@endif
        </x-slot:head>
        @foreach ($payments as $payment)
            <tr>
                <td class="whitespace-nowrap">{{ $payment->paid_on->format('d M Y') }}</td>
                <td class="num whitespace-nowrap">
                    <span class="font-medium text-ink">{{ \App\Support\Money\Money::format($payment->amount_paise) }}</span>
                    @if ($payment->differencePaise() !== 0)
                        <span class="block text-xs {{ $payment->differencePaise() < 0 ? 'text-warning-ink' : 'text-ink-faint' }}">
                            expected {{ \App\Support\Money\Money::format($payment->expected_paise) }}
                        </span>
                    @endif
                </td>
                <td><x-ui.badge :tone="$payment->mode->tone()">{{ $payment->mode->label() }}</x-ui.badge></td>
                <td class="font-mono text-[13px] text-ink-soft">{{ $payment->reference ?? '—' }}</td>
                <td class="whitespace-nowrap text-ink-soft">
                    {{ $payment->period_starts_on->format('d M Y') }} – {{ $payment->period_ends_on->format('d M Y') }}
                    <span class="block text-xs text-ink-faint">{{ $payment->months }} {{ \Illuminate\Support\Str::plural('month', $payment->months) }} · {{ $payment->plan->name }}</span>
                </td>
                @if ($showRecorder)
                    <td class="text-ink-soft">{{ $payment->recordedBy?->name ?? '—' }}</td>
                @endif
            </tr>
        @endforeach
    </x-ui.table>
@endif
