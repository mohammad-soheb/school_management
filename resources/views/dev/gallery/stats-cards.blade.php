<x-dev.section id="stats" title="Stats & cards" description="Stat tiles with tinted icon tiles; cards with header actions, flush tables and footers.">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Students" value="1,248" icon="academic-cap" tone="brand" delta="+4.2%" hint="vs last year" />
        <x-ui.stat label="Collected today" value="₹ 86,400" icon="banknotes" tone="success" delta="+12%" hint="42 receipts" href="#stats" />
        <x-ui.stat label="Overdue" value="₹ 3,12,500" icon="exclamation-triangle" tone="danger" delta="-3.1%" hint="64 students" />
        <x-ui.stat label="Present today" value="92%" icon="clipboard-document-check" tone="info" delta="0%" hint="of 1,176 marked" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Card with actions" description="Header actions sit on the right; the footer holds secondary info." class="lg:col-span-2">
            <x-slot:actions>
                <x-ui.button size="sm" variant="secondary" icon="pencil-square">Edit</x-ui.button>
                <x-ui.button size="sm" icon="plus">Add</x-ui.button>
            </x-slot:actions>

            <p class="text-ink-soft">Cards are white surfaces with a hairline border and a very soft shadow. In dark mode they become raised navy panels. Content uses the <code>ink</code> scale so contrast holds in both themes.</p>

            <x-slot:footer>
                <span class="mr-auto text-xs text-ink-faint">Last updated 29 Oct 2026, 9:15 am</span>
                <x-ui.button size="sm" variant="ghost">Cancel</x-ui.button>
                <x-ui.button size="sm">Save</x-ui.button>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Loading state" description="Skeletons keep the layout stable.">
            <div class="space-y-5" aria-busy="true">
                <div class="flex items-center gap-3">
                    <x-ui.skeleton variant="avatar" />
                    <div class="flex-1"><x-ui.skeleton :lines="2" /></div>
                </div>
                <x-ui.skeleton variant="block" />
                <x-ui.skeleton :lines="3" />
            </div>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Empty state" padding>
            <x-ui.empty icon="user-group" title="No students yet" description="Admit your first student, or import a class list from Excel.">
                <x-ui.button icon="plus">Admit student</x-ui.button>
                <x-ui.button variant="secondary" icon="arrow-up-tray">Import</x-ui.button>
            </x-ui.empty>
        </x-ui.card>

        <x-ui.card title="No results" description="After filtering">
            <x-ui.empty icon="magnifying-glass" title="No students match your filters" description="Try a different name or clear the filters.">
                <x-ui.button variant="secondary">Clear filters</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    </div>
</x-dev.section>
