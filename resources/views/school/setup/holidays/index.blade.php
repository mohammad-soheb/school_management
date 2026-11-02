@php
    $canManage = auth()->user()->can('setup.holidays.manage');
    $editable = $canManage && $year !== null && ! $year->isLocked();
    $types = collect(\App\Enums\SchoolSetup\HolidayType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    $dayNames = \App\Services\SchoolSetup\SchoolSettingsService::WEEKDAYS;
@endphp
<x-layouts.app title="School calendar">
    <x-ui.page-header title="School calendar" description="Holidays close the school; events and exam periods are shown for planning."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Calendar']]">
        @if ($editable)
            <x-slot:actions>
                <x-ui.button :href="route('school.setup.holidays.create')" icon="plus">Add to calendar</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @include('school.setup._nav')

    @error('holiday')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    @if ($year === null)
        <x-ui.card>
            <x-ui.empty icon="calendar" title="No academic year yet" description="Create an academic year before adding holidays.">
                @can('setup.years.manage')
                    <x-ui.button :href="route('school.setup.years.create')" icon="plus">New academic year</x-ui.button>
                @endcan
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.filters :reset="route('school.setup.holidays.index')" :search="false">
            <x-form.select name="year" label="Academic year" :options="$years->pluck('name', 'id')->all()" :selected="$year->id" />
            <x-form.select name="type" label="Type" placeholder="All types" :options="$types" :selected="$type?->value" />
        </x-ui.filters>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Working days" :value="number_format($workingDays)" icon="briefcase" tone="success" :hint="$year->name.' after holidays and weekly offs'" />
            <x-ui.stat label="Weekly off" :value="$weeklyOff === [] ? 'None' : implode(', ', array_map(fn ($d) => substr($dayNames[$d], 0, 3), $weeklyOff))" icon="moon" tone="neutral" hint="Change in Settings" :href="auth()->user()->can('setup.settings.manage') ? route('school.setup.settings.edit') : null" />
            <x-ui.stat label="Status" :value="$year->status->label()" :icon="$year->isLocked() ? 'lock-closed' : 'lock-open'" :tone="$year->isLocked() ? 'warning' : 'brand'"
                       :hint="$year->isLocked() ? 'Locked years are read-only' : $year->starts_on->format('d M Y').' – '.$year->ends_on->format('d M Y')" />
        </div>

        @if ($months === [])
            <x-ui.card class="mt-6">
                <x-ui.empty icon="calendar-days" :title="$type ? 'No '.strtolower($type->label()).' entries' : 'Nothing on the calendar yet'"
                            description="Add public holidays, vacations, events and exam periods for {{ $year->name }}.">
                    @if ($editable)
                        <x-ui.button :href="route('school.setup.holidays.create')" icon="plus">Add to calendar</x-ui.button>
                    @endif
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="mt-6 space-y-6">
                @foreach ($months as $month => $entries)
                    <x-ui.card :title="\Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('F Y')" :padding="false">
                        <x-slot:actions>
                            <x-ui.badge>{{ $entries->count() }}</x-ui.badge>
                        </x-slot:actions>
                        <ul class="divide-y divide-line">
                            @foreach ($entries as $entry)
                                @php $days = $entry->starts_on->diffInDays($entry->ends_on) + 1; @endphp
                                <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap">
                                    <div class="w-12 shrink-0 rounded-lg border border-line bg-surface-muted py-1 text-center">
                                        <span class="block text-[11px] font-semibold uppercase text-ink-faint">{{ $entry->starts_on->format('D') }}</span>
                                        <span class="block text-lg font-semibold leading-tight text-ink">{{ $entry->starts_on->format('d') }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-ink">{{ $entry->title }}</p>
                                        <p class="text-sm text-ink-faint">
                                            @if ($days === 1)
                                                {{ $entry->starts_on->format('l, d M Y') }}
                                            @else
                                                {{ $entry->starts_on->format('d M') }} – {{ $entry->ends_on->format('d M Y') }} · {{ (int) $days }} days
                                            @endif
                                        </p>
                                    </div>
                                    <x-ui.badge :tone="$entry->type->tone()" dot>{{ $entry->type->label() }}</x-ui.badge>
                                    @if ($editable)
                                        <div class="flex gap-1">
                                            <x-ui.button :href="route('school.setup.holidays.edit', $entry)" variant="ghost" size="sm" icon="pencil-square" class="btn-icon" :aria-label="'Edit '.$entry->title" />
                                            <x-ui.confirm :action="route('school.setup.holidays.destroy', $entry)" method="DELETE"
                                                          :title="'Remove '.$entry->title.'?'" message="It is taken off the calendar. Attendance already taken is not changed." confirm="Remove">
                                                <x-ui.button variant="ghost" size="sm" icon="trash" class="btn-icon" :aria-label="'Remove '.$entry->title" />
                                            </x-ui.confirm>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.app>
