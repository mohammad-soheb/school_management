<x-layouts.app title="Audit log">
    <x-ui.page-header title="Audit log" description="Who changed what, and when. Entries can never be edited or deleted." />

    <x-ui.filters :reset="route('school.access.audit.index')" :search="false">
        <x-form.input name="event" label="Event starts with" :value="$filters['event'] ?? null" placeholder="e.g. student. or fee." />
        <x-form.date name="from" label="From" :value="$filters['from'] ?? null" />
        <x-form.date name="to" label="To" :value="$filters['to'] ?? null" />
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($events->isEmpty())
            <x-ui.empty icon="clipboard-document-list" title="No matching events" />
        @else
            <x-ui.table caption="Audit events" dense>
                <x-slot:head>
                    <x-ui.th>When</x-ui.th>
                    <x-ui.th>Who</x-ui.th>
                    <x-ui.th>Event</x-ui.th>
                    <x-ui.th>Record</x-ui.th>
                    <x-ui.th>Details</x-ui.th>
                </x-slot:head>
                @foreach ($events as $event)
                    <tr>
                        <td class="whitespace-nowrap text-sm text-ink-soft">{{ $event->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}</td>
                        <td class="text-sm">{{ $event->actor?->name ?? 'System' }}</td>
                        <td><code class="rounded bg-surface-muted px-1.5 py-0.5 font-mono text-[12px]">{{ $event->event }}</code></td>
                        <td class="text-sm text-ink-soft">{{ $event->subject_type ? class_basename($event->subject_type).' #'.$event->subject_id : '—' }}</td>
                        <td class="text-sm">
                            @if ($event->old || $event->new || $event->meta)
                                <details>
                                    <summary class="cursor-pointer font-medium text-brand-ink hover:underline">View</summary>
                                    <pre class="mt-2 max-w-md overflow-x-auto rounded-lg bg-surface-muted p-2 text-[12px]">{{ json_encode(array_filter(['before' => $event->old, 'after' => $event->new, 'meta' => $event->meta]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <span class="text-ink-faint">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($events->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $events->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
