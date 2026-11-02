<x-ui.table caption="Schools">
    <x-slot:head>
        <x-ui.th>School</x-ui.th>
        <x-ui.th>Code</x-ui.th>
        <x-ui.th>City</x-ui.th>
        <x-ui.th>Status</x-ui.th>
        <x-ui.th>Added</x-ui.th>
        <th class="col-actions"><span class="sr-only">Actions</span></th>
    </x-slot:head>
    @foreach ($schools as $school)
        <tr>
            <td>
                <a href="{{ route('platform.schools.show', $school) }}" class="flex items-center gap-3">
                    <x-ui.avatar :name="$school->name" size="sm" />
                    <span class="font-medium text-ink hover:underline">{{ $school->name }}</span>
                </a>
            </td>
            <td><code class="font-mono text-[13px] text-ink-soft">{{ $school->code }}</code></td>
            <td class="text-ink-soft">{{ $school->city ?? '—' }}</td>
            <td><x-ui.badge :tone="$school->status->tone()" dot>{{ $school->status->label() }}</x-ui.badge></td>
            <td class="text-ink-soft">{{ $school->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
            <td class="col-actions">
                <x-ui.button :href="route('platform.schools.show', $school)" variant="ghost" size="sm">Open</x-ui.button>
            </td>
        </tr>
    @endforeach
</x-ui.table>
