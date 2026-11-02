<x-dev.section id="navigation" title="Navigation" description="Server-driven link tabs (each tab is its own URL), breadcrumbs from the page header, and dropdown menus.">
    <div class="space-y-6">
        <x-ui.card title="Underline tabs">
            <x-ui.tabs label="Student sections" :tabs="[
                ['label' => 'Overview', 'url' => '#navigation', 'active' => true, 'icon' => 'user'],
                ['label' => 'Fees', 'url' => '#navigation', 'badge' => 3, 'icon' => 'banknotes'],
                ['label' => 'Attendance', 'url' => '#navigation', 'icon' => 'calendar-days'],
                ['label' => 'Documents', 'url' => '#navigation', 'icon' => 'document-text'],
            ]" />
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Pill tabs">
                <x-ui.tabs pill label="Period" :tabs="[
                    ['label' => 'Today', 'url' => '#navigation', 'active' => true],
                    ['label' => 'This week', 'url' => '#navigation'],
                    ['label' => 'This month', 'url' => '#navigation'],
                ]" />
            </x-ui.card>

            <x-ui.card title="Dropdown menu">
                <x-ui.dropdown align="left" width="56" label="Student actions">
                    <x-slot:trigger>
                        <x-ui.button variant="secondary" icon-right="chevron-down">Actions</x-ui.button>
                    </x-slot:trigger>
                    <p class="dropdown-label">Student</p>
                    <x-ui.dropdown-link href="#navigation" icon="pencil-square">Edit details</x-ui.dropdown-link>
                    <x-ui.dropdown-link href="#navigation" icon="printer">Print ID card</x-ui.dropdown-link>
                    <hr class="dropdown-divider">
                    <x-ui.dropdown-link as="button" type="button" icon="archive-box" danger>Mark as left</x-ui.dropdown-link>
                </x-ui.dropdown>
            </x-ui.card>
        </div>

        <x-ui.card title="Page header with breadcrumbs">
            <x-ui.page-header title="Aarav Sharma" description="Adm. 1042 · Class 5-A · Roll 12"
                              :breadcrumbs="[['label' => 'Students', 'url' => '#navigation'], ['label' => 'Class 5-A', 'url' => '#navigation'], ['label' => 'Aarav Sharma']]">
                <div class="flex flex-wrap gap-2">
                    <x-ui.badge tone="success" dot>Active</x-ui.badge>
                    <x-ui.badge tone="info">Bus route 4</x-ui.badge>
                </div>
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="pencil-square">Edit</x-ui.button>
                    <x-ui.button icon="banknotes">Collect fee</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>
        </x-ui.card>
    </div>
</x-dev.section>
