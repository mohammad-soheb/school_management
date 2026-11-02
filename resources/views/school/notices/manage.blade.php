@php
    $tz = config('app.display_timezone');
    $state = $filters['state'] ?? null;
    $tabs = [['label' => 'All', 'url' => route('school.notices.manage'), 'active' => $state === null]];
    foreach (\App\Services\Communication\NoticeService::STATES as $value => $label) {
        $tabs[] = ['label' => $label, 'url' => route('school.notices.manage', ['state' => $value]), 'active' => $state === $value];
    }
@endphp
<x-layouts.app title="Manage notices">
    <x-ui.page-header title="Manage notices" description="Drafts, scheduled, live, expired and archived notices."
        :breadcrumbs="[['label' => 'Notice board', 'url' => route('school.notices.index')], ['label' => 'Manage']]">
        <x-slot:actions>
            <x-ui.button :href="route('school.notices.create')" icon="plus">New notice</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs pill class="mb-4" label="Notice state" :tabs="$tabs" />

    <x-ui.filters :reset="route('school.notices.manage', array_filter(['state' => $state]))" placeholder="Search by title">
        @if ($state)
            <input type="hidden" name="state" value="{{ $state }}">
        @endif
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($notices->isEmpty())
            <x-ui.empty icon="megaphone" :title="$state ? 'No '.strtolower(\App\Services\Communication\NoticeService::STATES[$state]).' notices' : 'No notices yet'"
                        description="Write a notice for the whole school, staff, parents, or chosen classes.">
                <x-ui.button :href="route('school.notices.create')" icon="plus">New notice</x-ui.button>
            </x-ui.empty>
        @else
            <x-ui.table caption="Notices">
                <x-slot:head>
                    <x-ui.th>Notice</x-ui.th>
                    <x-ui.th>Audience</x-ui.th>
                    <x-ui.th>State</x-ui.th>
                    <x-ui.th>Shows</x-ui.th>
                    <x-ui.th>Created by</x-ui.th>
                </x-slot:head>
                @foreach ($notices as $notice)
                    <tr>
                        <td class="max-w-xs">
                            <a href="{{ route('school.notices.show', $notice) }}" class="font-medium text-ink hover:text-brand-ink">{{ $notice->title }}</a>
                            @if ($notice->is_pinned)
                                <x-ui.icon name="bookmark" variant="m" class="ml-1 inline h-3.5 w-3.5 text-brand-ink" title="Pinned" />
                            @endif
                            @if ($notice->attachments_count > 0)
                                <span class="ml-1 inline-flex items-center gap-0.5 text-xs text-ink-faint"><x-ui.icon name="paper-clip" class="h-3.5 w-3.5" />{{ $notice->attachments_count }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($notice->audiences as $audience)
                                    <x-ui.badge :tone="$audience->type->tone()">{{ $audience->label() }}</x-ui.badge>
                                @empty
                                    <span class="text-sm text-ink-faint">Not set</span>
                                @endforelse
                            </div>
                        </td>
                        <td><x-ui.badge :tone="$notice->stateTone()" dot>{{ $notice->stateLabel() }}</x-ui.badge></td>
                        <td class="whitespace-nowrap text-sm text-ink-soft">
                            {{ $notice->publish_at ? $notice->publish_at->timezone($tz)->format('d M, h:i A') : 'On publish' }}
                            @if ($notice->expires_at)
                                <span class="block text-xs text-ink-faint">until {{ $notice->expires_at->timezone($tz)->format('d M, h:i A') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-sm text-ink-soft">{{ $notice->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($notices->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $notices->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
