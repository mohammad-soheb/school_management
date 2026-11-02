@php
    $canManage = auth()->user()->can('notices.manage');
    $tz = config('app.display_timezone');
@endphp
<x-layouts.app title="Notice board">
    <x-ui.page-header title="Notice board" description="Notices and circulars addressed to you.">
        <x-slot:actions>
            @if ($canManage)
                <x-ui.button :href="route('school.notices.manage')" variant="secondary" icon="adjustments-horizontal">Manage notices</x-ui.button>
                <x-ui.button :href="route('school.notices.create')" icon="plus">New notice</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters :reset="route('school.notices.index')" placeholder="Search notices" />

    <div class="mt-4 space-y-4">
        @forelse ($notices as $notice)
            <article @class(['card comms-notice', 'is-pinned' => $notice->is_pinned]) aria-labelledby="notice-{{ $notice->id }}-title">
                <header class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 id="notice-{{ $notice->id }}-title" class="text-base font-semibold text-ink">
                            <a href="{{ route('school.notices.show', $notice) }}" class="hover:text-brand-ink">{{ $notice->title }}</a>
                        </h2>
                        <p class="mt-0.5 text-xs text-ink-faint">
                            {{ ($notice->publish_at ?? $notice->published_at ?? $notice->created_at)->timezone($tz)->format('d M Y, h:i A') }}
                            @if ($notice->publisher) · {{ $notice->publisher->name }} @endif
                            @if ($notice->expires_at) · until {{ $notice->expires_at->timezone($tz)->format('d M, h:i A') }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @if ($notice->is_pinned)
                            <x-ui.badge tone="brand"><x-ui.icon name="bookmark" variant="m" class="h-3.5 w-3.5" /> Pinned</x-ui.badge>
                        @endif
                        @foreach ($notice->audiences as $audience)
                            <x-ui.badge :tone="$audience->type->tone()">{{ $audience->label() }}</x-ui.badge>
                        @endforeach
                    </div>
                </header>

                <div class="comms-body comms-body-clamp mt-3">{!! nl2br(e($notice->body)) !!}</div>

                <footer class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <a href="{{ route('school.notices.show', $notice) }}" class="font-medium text-brand-ink hover:underline">Read notice</a>
                    @foreach ($notice->attachments as $file)
                        <a href="{{ route('school.notices.attachments.show', [$notice, $file]) }}" class="inline-flex items-center gap-1 text-ink-soft hover:text-ink">
                            <x-ui.icon name="paper-clip" class="h-4 w-4" /><span class="max-w-[14rem] truncate">{{ $file->original_name }}</span>
                        </a>
                    @endforeach
                </footer>
            </article>
        @empty
            <x-ui.card>
                @if (request()->filled('q'))
                    <x-ui.empty icon="magnifying-glass" title="No notices match your search" />
                @else
                    <x-ui.empty icon="megaphone" title="No notices right now" description="Notices for you, your role or the classes you teach will appear here.">
                        @if ($canManage)
                            <x-ui.button :href="route('school.notices.create')" icon="plus">New notice</x-ui.button>
                        @endif
                    </x-ui.empty>
                @endif
            </x-ui.card>
        @endforelse
    </div>

    @if ($notices->hasPages())
        <div class="mt-4">{{ $notices->links() }}</div>
    @endif
</x-layouts.app>
