@php
    $user = auth()->user();
    $today = \App\Services\Attendance\AttendanceCalendar::today();
    $isToday = $date->isSameDay($today);
    $tz = config('app.display_timezone');
    $percent = $totals['summary']['percentage'];
    $dayUrl = fn ($day) => route('school.attendance.index', ['date' => $day->toDateString()]);
@endphp
<x-layouts.app title="Attendance">
    <x-ui.page-header title="Attendance" :description="($isToday ? 'Today · ' : '').$date->format('l, d M Y')">
        <x-slot:actions>
            @can('attendance.staff.access')
                <x-ui.button :href="route('school.attendance.staff.index', ['date' => $date->toDateString()])" variant="secondary" icon="identification">Staff</x-ui.button>
            @endcan
            @can('attendance.reports.view')
                <x-ui.button :href="route('school.attendance.reports.daily', ['date' => $date->toDateString()])" variant="secondary" icon="chart-bar">Reports</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('school.attendance.index') }}" data-lock="false" class="att-datebar mb-5">
        @if ($previous)
            <x-ui.button :href="$dayUrl($previous)" variant="secondary" icon="chevron-left" class="btn-icon min-h-touch" aria-label="Previous school day ({{ $previous->format('d M') }})" />
        @endif
        <x-form.date name="date" label="Date" :value="$date->toDateString()" max="{{ $today->toDateString() }}" onchange="this.form.requestSubmit()" />
        @if ($next)
            <x-ui.button :href="$dayUrl($next)" variant="secondary" icon="chevron-right" class="btn-icon min-h-touch" aria-label="Next school day ({{ $next->format('d M') }})" />
        @endif
        <x-ui.button type="submit" variant="secondary" class="min-h-touch">Go</x-ui.button>
        @unless ($isToday)
            <x-ui.button :href="route('school.attendance.index')" variant="ghost" class="min-h-touch">Today</x-ui.button>
        @endunless
    </form>

    @if ($non_working)
        <x-ui.alert tone="info" title="No school on this day" class="mb-5">{{ $date->format('D, d M Y') }}: {{ $non_working }}. Attendance is not taken.</x-ui.alert>
    @elseif ($marking_error && collect($rows)->contains(fn ($row) => $row['can_mark'] && ! $row['sheet']))
        <x-ui.alert tone="warning" class="mb-5">{{ $marking_error }}</x-ui.alert>
    @endif

    @if (count($rows) === 0)
        <x-ui.card>
            <x-ui.empty icon="clipboard-document-check" title="No classes to show"
                        description="You are not assigned to any class for the current academic year. Ask the school admin to assign your classes." />
        </x-ui.card>
    @else
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-ui.stat label="Classes marked" :value="$totals['marked'].' / '.$totals['sections']" icon="clipboard-document-check" tone="success" />
            <x-ui.stat label="Not marked" :value="$totals['pending']" icon="clock" :tone="$totals['pending'] > 0 ? 'warning' : 'neutral'" />
            <x-ui.stat label="Attendance" :value="$percent === null ? '—' : number_format($percent, 1).'%'" icon="chart-pie" tone="brand" />
            <x-ui.stat label="Absent" :value="$totals['summary']['absent']" icon="user-minus" tone="danger" />
        </div>

        <ul class="att-section-grid" aria-label="Classes">
            @foreach ($rows as $row)
                @php
                    $section = $row['section'];
                    $sheet = $row['sheet'];
                    $state = $sheet ? 'done' : ($row['roster'] === 0 ? 'empty' : 'pending');
                    $url = $sheet ? route('school.attendance.sheets.show', $sheet) : route('school.attendance.mark', [$section, 'date' => $date->toDateString()]);
                @endphp
                <li>
                    <a href="{{ $url }}" @class(['att-section-card', 'is-pending' => $state === 'pending' && ! $non_working])>
                        <span @class(['att-section-mark', 'is-'.$state])>
                            @if ($state === 'done')
                                <x-ui.icon name="check" />
                            @elseif ($state === 'pending')
                                <x-ui.icon name="clock" />
                            @else
                                <x-ui.icon name="minus" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-ink">{{ $section->label() }}</span>
                            <span class="block truncate text-sm text-ink-faint">
                                @if ($sheet)
                                    {{ $row['summary']['present'] + $row['summary']['late'] }} present · {{ $row['summary']['absent'] }} absent
                                    @if ($row['summary']['leave'] > 0) · {{ $row['summary']['leave'] }} leave @endif
                                    · {{ $sheet->submitted_at?->timezone($tz)->format('h:i A') }}
                                @elseif ($row['roster'] === 0)
                                    No students enrolled
                                @else
                                    {{ $row['roster'] }} students · {{ $row['can_mark'] ? 'not marked yet' : 'not marked (view only)' }}
                                @endif
                            </span>
                        </span>
                        @if ($sheet)
                            <x-ui.badge tone="success">Marked</x-ui.badge>
                        @elseif ($state === 'pending' && $row['can_mark'] && ! $marking_error)
                            <span class="btn btn-primary btn-sm">Mark</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
