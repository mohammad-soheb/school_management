@php
    $menu = [[
        'label' => 'Parent portal',
        'items' => [
            ['label' => 'Home', 'url' => '#', 'icon' => 'home', 'active' => true, 'badge' => null],
            ['label' => 'Attendance', 'url' => '#', 'icon' => 'calendar-days', 'active' => false, 'badge' => null],
            ['label' => 'Fees', 'url' => '#', 'icon' => 'banknotes', 'active' => false, 'badge' => '1'],
            ['label' => 'Homework', 'url' => '#', 'icon' => 'book-open', 'active' => false, 'badge' => null],
            ['label' => 'Results', 'url' => '#', 'icon' => 'chart-bar', 'active' => false, 'badge' => null],
            ['label' => 'Notices', 'url' => '#', 'icon' => 'megaphone', 'active' => false, 'badge' => '3'],
        ],
    ]];
    $children = [
        ['id' => 11, 'name' => 'Aarav Sharma', 'class' => '5-A', 'active' => true],
        ['id' => 12, 'name' => 'Diya Sharma', 'class' => '2-B', 'active' => false],
    ];
    $demoUser = auth()->user() ?? new \App\Models\User(['name' => 'Rohit Sharma', 'email' => 'rohit.sharma@example.test']);
@endphp
<x-layouts.portal title="Home" :children="$children" :menu="$menu" :user="$demoUser">
    <div class="mb-5">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Good morning, Rohit</h1>
        <p class="mt-1 text-ink-soft">Here is how Aarav is doing this month.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Attendance this month" value="94%" icon="calendar-days" tone="success" hint="21 of 22 days present" />
        <x-ui.stat label="Fees due" value="₹4,250" icon="banknotes" tone="warning" hint="Term 2 · due 10 Nov" />
    </div>

    <x-ui.card class="mt-4" title="Today" description="Wednesday, 29 October">
        <ul class="list-rows">
            <li>
                <span class="stat-icon stat-icon-brand !h-10 !w-10"><x-ui.icon name="book-open" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-ink">Mathematics worksheet 7</p>
                    <p class="text-sm text-ink-soft">Due tomorrow</p>
                </div>
                <x-ui.badge tone="info">Homework</x-ui.badge>
            </li>
            <li>
                <span class="stat-icon stat-icon-success !h-10 !w-10"><x-ui.icon name="check-circle" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-ink">Marked present</p>
                    <p class="text-sm text-ink-soft">Arrived at 8:02 am</p>
                </div>
                <x-ui.badge tone="success" dot>Present</x-ui.badge>
            </li>
        </ul>
        <x-slot:footer>
            <span class="portal-updated"><x-ui.icon name="clock" variant="m" />Updated today, 9:15 am</span>
        </x-slot:footer>
    </x-ui.card>

    <x-ui.card class="mt-4" title="Pay fees" description="Show this at the school office or pay at the counter.">
        <x-ui.button size="lg" block icon="banknotes">View fee details</x-ui.button>
    </x-ui.card>
</x-layouts.portal>
