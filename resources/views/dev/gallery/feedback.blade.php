<x-dev.section id="feedback" title="Badges & alerts" description="Tones carry meaning: success = done/paid, warning = needs attention, danger = failed/overdue, info = neutral news, brand = emphasis.">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Badges">
            <div class="flex flex-wrap items-center gap-2.5">
                <x-ui.badge>Neutral</x-ui.badge>
                <x-ui.badge tone="brand">Brand</x-ui.badge>
                <x-ui.badge tone="success">Success</x-ui.badge>
                <x-ui.badge tone="warning">Warning</x-ui.badge>
                <x-ui.badge tone="danger">Danger</x-ui.badge>
                <x-ui.badge tone="info">Info</x-ui.badge>
            </div>
            <p class="label mt-6">With a status dot</p>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-ui.badge tone="neutral" dot>Draft</x-ui.badge>
                <x-ui.badge tone="success" dot>Paid</x-ui.badge>
                <x-ui.badge tone="warning" dot>Partially paid</x-ui.badge>
                <x-ui.badge tone="danger" dot>Overdue</x-ui.badge>
                <x-ui.badge tone="info" dot>Scheduled</x-ui.badge>
                <x-ui.badge tone="brand" dot>Current year</x-ui.badge>
            </div>
            <p class="label mt-6">Status to tone convention</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div class="flex items-center gap-2"><x-ui.badge tone="success" dot>Present</x-ui.badge></div><div class="flex items-center gap-2"><x-ui.badge tone="danger" dot>Absent</x-ui.badge></div>
                <div class="flex items-center gap-2"><x-ui.badge tone="warning" dot>Late</x-ui.badge></div><div class="flex items-center gap-2"><x-ui.badge tone="info" dot>On leave</x-ui.badge></div>
                <div class="flex items-center gap-2"><x-ui.badge tone="success" dot>Active</x-ui.badge></div><div class="flex items-center gap-2"><x-ui.badge tone="neutral" dot>Inactive</x-ui.badge></div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Alerts">
            <div class="space-y-3">
                <x-ui.alert tone="info" title="Heads up">Results are published on 5 November.</x-ui.alert>
                <x-ui.alert tone="success" title="Receipt saved" dismissible>Receipt R-2026-1042 was issued to Aarav Sharma.</x-ui.alert>
                <x-ui.alert tone="warning" title="Three instalments are overdue">Reminders have not been sent yet.</x-ui.alert>
                <x-ui.alert tone="danger" title="Import failed">12 rows had errors. Download the error file and fix them.</x-ui.alert>
                <x-ui.alert tone="brand">A new academic year can be started from School setup.</x-ui.alert>
            </div>
        </x-ui.card>
    </div>
</x-dev.section>
