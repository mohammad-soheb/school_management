@php
    $rows = [
        ['adm' => '1042', 'name' => 'Aarav Sharma', 'class' => '5-A', 'balance' => '₹ 0.00', 'status' => ['Paid', 'success']],
        ['adm' => '1043', 'name' => 'Aanya Iyer', 'class' => '5-B', 'balance' => '₹ 4,250.00', 'status' => ['Partially paid', 'warning']],
        ['adm' => '1107', 'name' => 'Vihaan Patel', 'class' => '7-A', 'balance' => '₹ 12,800.00', 'status' => ['Overdue', 'danger']],
        ['adm' => '1210', 'name' => 'Diya Menon', 'class' => '3-C', 'balance' => '₹ 0.00', 'status' => ['Paid', 'success']],
    ];
    $pagerItems = collect(range(1, 25));
    $pager = new \Illuminate\Pagination\LengthAwarePaginator($pagerItems, 248, 25, 4, ['path' => url('/_ui'), 'fragment' => 'tables']);
    $simplePager = new \Illuminate\Pagination\Paginator($pagerItems, 25, 2, ['path' => url('/_ui'), 'fragment' => 'tables']);
@endphp
<x-dev.section id="tables" title="Tables" description="Cards with padding=false hold tables edge to edge. The table scrolls sideways on its own; the page never does.">
    <x-ui.card title="Fee balances" description="Sortable headers keep the other query parameters." :padding="false">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" icon="arrow-down-tray">Export</x-ui.button>
        </x-slot:actions>

        <x-ui.table caption="Fee balances">
            <x-slot:head>
                <x-ui.th column="admission_no">Adm. no.</x-ui.th>
                <x-ui.th column="name" label="Student" />
                <x-ui.th>Class</x-ui.th>
                <x-ui.th column="balance" class="num">Balance</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr>
                    <td class="tabular-nums text-ink-soft">{{ $row['adm'] }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$row['name']" size="sm" aria-hidden="true" />
                            <span class="font-medium text-ink">{{ $row['name'] }}</span>
                        </div>
                    </td>
                    <td>{{ $row['class'] }}</td>
                    <td class="num">{{ $row['balance'] }}</td>
                    <td><x-ui.badge :tone="$row['status'][1]" dot>{{ $row['status'][0] }}</x-ui.badge></td>
                    <td class="col-actions">
                        <x-ui.dropdown align="right" width="48" label="Actions for {{ $row['name'] }}">
                            <x-slot:trigger>
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Actions for {{ $row['name'] }}"><x-ui.icon name="ellipsis-horizontal" /></button>
                            </x-slot:trigger>
                            <x-ui.dropdown-link href="#tables" icon="eye">View ledger</x-ui.dropdown-link>
                            <x-ui.dropdown-link href="#tables" icon="banknotes">Collect fee</x-ui.dropdown-link>
                        </x-ui.dropdown>
                    </td>
                </tr>
            @endforeach

            <x-slot:foot>
                <tr>
                    <td colspan="3" class="font-semibold text-ink">Total</td>
                    <td class="num font-semibold text-ink">₹ 17,050.00</td>
                    <td colspan="2"></td>
                </tr>
            </x-slot:foot>
        </x-ui.table>

        <x-slot:footer>
            <div class="w-full">{{ $pager->links() }}</div>
        </x-slot:footer>
    </x-ui.card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Dense table" :padding="false">
            <x-ui.table caption="Dense example" dense>
                <x-slot:head>
                    <x-ui.th>Date</x-ui.th>
                    <x-ui.th>Receipt</x-ui.th>
                    <x-ui.th class="num">Amount</x-ui.th>
                </x-slot:head>
                <tr><td>28 Oct 2026</td><td class="tabular-nums">R-2026-1041</td><td class="num">₹ 8,500.00</td></tr>
                <tr><td>27 Oct 2026</td><td class="tabular-nums">R-2026-1040</td><td class="num">₹ 1,200.00</td></tr>
                <tr><td>27 Oct 2026</td><td class="tabular-nums">R-2026-1039</td><td class="num">₹ 50.00</td></tr>
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Simple pagination" description="->simplePaginate(): no total, Previous / Page n / Next.">
            {{ $simplePager->links() }}
        </x-ui.card>
    </div>
</x-dev.section>
