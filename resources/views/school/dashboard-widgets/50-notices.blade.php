{{--
    Dashboard widget (homework & notices module): latest notices addressed to this user, and - for anyone who sets
    homework - their homework due this week. Receives $user and $school.
--}}
@php
    $showNotices = $user->can('notices.access');
    $showHomework = $user->can('homework.author');
    $boardNotices = $showNotices ? app(\App\Services\Communication\NoticeFeed::class)->forStaff($user, 5) : collect();
    $dueHomework = $showHomework ? app(\App\Services\Communication\HomeworkService::class)->dueThisWeek($user, 6) : collect();
    $tz = config('app.display_timezone');
@endphp
@if ($showNotices || $showHomework)
    <div @class(['grid gap-6', 'lg:grid-cols-2' => $showNotices && $showHomework])>
        @if ($showNotices)
            <x-ui.card title="Notices" :padding="false">
                <x-slot:actions>
                    <x-ui.button size="sm" variant="ghost" :href="route('school.notices.index')" icon-right="arrow-right">Notice board</x-ui.button>
                </x-slot:actions>
                @if ($boardNotices->isEmpty())
                    <x-ui.empty icon="megaphone" title="No notices right now" class="py-8" />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($boardNotices as $notice)
                            <li>
                                <a href="{{ route('school.notices.show', $notice) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface-muted">
                                    <x-ui.icon :name="$notice->is_pinned ? 'bookmark' : 'megaphone'" :variant="$notice->is_pinned ? 's' : 'o'" :class="'mt-0.5 h-5 w-5 shrink-0 '.($notice->is_pinned ? 'text-brand-ink' : 'text-ink-faint')" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-ink">{{ $notice->title }}</span>
                                        <span class="block truncate text-sm text-ink-faint">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $notice->body), 90) }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-ink-faint">{{ ($notice->publish_at ?? $notice->published_at ?? $notice->created_at)->timezone($tz)->format('d M') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif

        @if ($showHomework)
            <x-ui.card title="Homework due this week" :padding="false">
                <x-slot:actions>
                    <x-ui.button size="sm" variant="ghost" :href="route('school.homework.index', ['due' => 'week', 'mine' => 1])" icon-right="arrow-right">All homework</x-ui.button>
                </x-slot:actions>
                @if ($dueHomework->isEmpty())
                    <x-ui.empty icon="book-open" title="Nothing due this week" class="py-8">
                        <x-ui.button size="sm" :href="route('school.homework.create')" icon="plus">Set homework</x-ui.button>
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($dueHomework as $item)
                            <li>
                                <a href="{{ route('school.homework.show', $item) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-muted">
                                    <span class="comms-date" aria-hidden="true">
                                        <span class="comms-date-day">{{ $item->due_on->format('d') }}</span>
                                        <span class="comms-date-month">{{ $item->due_on->format('D') }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-ink">{{ $item->title }}</span>
                                        <span class="block truncate text-sm text-ink-faint">{{ $item->subject->name }} · {{ $item->audienceLabel() }}</span>
                                    </span>
                                    @if ($item->isOverdue())
                                        <x-ui.badge tone="danger">Past due</x-ui.badge>
                                    @elseif ($item->isDueToday())
                                        <x-ui.badge tone="warning">Today</x-ui.badge>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    </div>
@endif
