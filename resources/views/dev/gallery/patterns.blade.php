<x-dev.section id="patterns" title="Misc" description="Description lists, avatars, copy buttons and list rows.">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Description list">
            <x-ui.dl>
                <x-ui.dl-item label="Admission no.">1042</x-ui.dl-item>
                <x-ui.dl-item label="Class">5-A (Roll 12)</x-ui.dl-item>
                <x-ui.dl-item label="Date of birth">14 Jun 2015</x-ui.dl-item>
                <x-ui.dl-item label="Blood group"></x-ui.dl-item>
                <x-ui.dl-item label="Guardian">Rohit Sharma <x-ui.badge tone="success">Verified</x-ui.badge></x-ui.dl-item>
            </x-ui.dl>
        </x-ui.card>

        <x-ui.card title="Description grid">
            <x-ui.dl grid>
                <x-ui.dl-item label="Total due">₹ 18,500.00</x-ui.dl-item>
                <x-ui.dl-item label="Paid">₹ 14,250.00</x-ui.dl-item>
                <x-ui.dl-item label="Balance">₹ 4,250.00</x-ui.dl-item>
                <x-ui.dl-item label="Last receipt">R-2026-1042</x-ui.dl-item>
            </x-ui.dl>
        </x-ui.card>

        <x-ui.card title="Avatars">
            <div class="flex flex-wrap items-end gap-3">
                <x-ui.avatar name="Aarav Sharma" size="xs" />
                <x-ui.avatar name="Aanya Iyer" size="sm" />
                <x-ui.avatar name="Vihaan Patel" size="md" />
                <x-ui.avatar name="Diya Menon" size="lg" />
                <x-ui.avatar name="Kabir Khan" size="xl" />
                <x-ui.avatar name="" size="md" />
            </div>
        </x-ui.card>

        <x-ui.card title="List rows and copy">
            <ul class="list-rows">
                <li>
                    <x-ui.avatar name="Priya Nair" size="md" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink">Priya Nair</p>
                        <p class="text-sm text-ink-soft">School admin · priya.nair@sunrise.example</p>
                    </div>
                    <x-ui.badge tone="brand">Admin</x-ui.badge>
                </li>
                <li>
                    <x-ui.avatar name="Rahul Verma" size="md" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink">Rahul Verma</p>
                        <p class="text-sm text-ink-soft">Accountant</p>
                    </div>
                    <x-ui.copy text="https://erp.example/invite/demo-token" label="Copy invite link" />
                </li>
            </ul>
        </x-ui.card>
    </div>
</x-dev.section>
