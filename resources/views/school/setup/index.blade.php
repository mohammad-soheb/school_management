@php
    $user = auth()->user();
    $done = collect($checklist)->where('done', true)->count();
    $total = count($checklist);
    $year = $counts['current_year'];
@endphp
<x-layouts.app title="School setup">
    <x-ui.page-header title="School setup" description="Academic years, classes, subjects and the calendar every other module builds on." />

    @include('school.setup._nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat label="Current year" :value="$year?->name ?? 'Not set'" icon="calendar" :tone="$year ? 'brand' : 'warning'"
                   :hint="$year ? $year->starts_on->format('d M Y').' – '.$year->ends_on->format('d M Y') : 'Set one to start'"
                   :href="route('school.setup.years.index')" />
        <x-ui.stat label="Classes" :value="number_format($counts['classes'])" icon="rectangle-group" tone="info" hint="active" :href="route('school.setup.classes.index')" />
        <x-ui.stat label="Sections" :value="number_format($counts['sections'])" icon="squares-2x2" tone="info" hint="active" :href="route('school.setup.classes.index')" />
        <x-ui.stat label="Subjects" :value="number_format($counts['subjects'])" icon="book-open" tone="success" hint="active" :href="route('school.setup.subjects.index')" />
        <x-ui.stat label="Calendar entries" :value="number_format($counts['holidays'])" icon="calendar-days" tone="neutral" hint="this year" :href="route('school.setup.holidays.index')" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Getting started" :description="$done === $total ? 'Everything is in place.' : $done.' of '.$total.' steps done'" class="lg:col-span-2">
            <x-ui.progress :value="$done" :max="$total" :tone="$done === $total ? 'success' : 'brand'" label="Setup progress" class="mb-5" />

            <ol class="divide-y divide-line">
                @foreach ($checklist as $step)
                    <li class="flex items-start gap-3 py-3">
                        @if ($step['done'])
                            <x-ui.icon name="check-circle" variant="s" class="mt-0.5 h-5 w-5 shrink-0 text-success-ink" aria-label="Done" />
                        @else
                            <x-ui.icon name="ellipsis-horizontal-circle" class="mt-0.5 h-5 w-5 shrink-0 text-ink-faint" aria-label="To do" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <p @class(['font-medium', 'text-ink' => ! $step['done'], 'text-ink-soft line-through decoration-line-strong' => $step['done']])>{{ $step['label'] }}</p>
                            <p class="text-sm text-ink-faint">{{ $step['hint'] }}</p>
                        </div>
                        @if (! $step['done'] && $user->can($step['can']))
                            <x-ui.button :href="route($step['route'])" variant="secondary" size="sm" icon-right="arrow-right">Open</x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <x-ui.card title="Coming up" description="Next holidays, events and exam periods">
            @if ($upcoming->isEmpty())
                <x-ui.empty icon="calendar-days" title="Nothing scheduled" description="Add holidays so attendance skips them.">
                    @can('setup.holidays.manage')
                        <x-ui.button :href="route('school.setup.holidays.create')" size="sm" icon="plus">Add to calendar</x-ui.button>
                    @endcan
                </x-ui.empty>
            @else
                <ul class="space-y-3">
                    @foreach ($upcoming as $entry)
                        <li class="flex items-start gap-3">
                            <div class="w-12 shrink-0 rounded-lg border border-line bg-surface-muted py-1 text-center">
                                <span class="block text-[11px] font-semibold uppercase text-ink-faint">{{ $entry->starts_on->format('M') }}</span>
                                <span class="block text-lg font-semibold leading-tight text-ink">{{ $entry->starts_on->format('d') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">{{ $entry->title }}</p>
                                <p class="text-sm text-ink-faint">
                                    {{ $entry->starts_on->format('D, d M') }}@if (! $entry->ends_on->isSameDay($entry->starts_on)) – {{ $entry->ends_on->format('D, d M') }}@endif
                                </p>
                            </div>
                            <x-ui.badge :tone="$entry->type->tone()">{{ $entry->type->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
