@php
    $today = \App\Services\Attendance\AttendanceCalendar::today();
    $isToday = $date->isSameDay($today);
    $tz = config('app.display_timezone');
    $canMark = auth()->user()->can('attendance.staff.mark');
    $statusCases = \App\Enums\Attendance\AttendanceStatus::cases();
    $pendingRows = array_values(array_filter($rows, fn ($row) => $row['record'] === null));
    $markedRows = array_values(array_filter($rows, fn ($row) => $row['record'] !== null));
    $canSubmit = $canMark && $marking_error === null && count($pendingRows) > 0;
    $dayUrl = fn ($day) => route('school.attendance.staff.index', ['date' => $day->toDateString()]);
    $staffLine = fn ($staff) => collect([$staff->employee_id, $staff->designation])->filter()->implode(' · ');
    $initials = fn ($staff) => mb_strtoupper(mb_substr($staff->first_name, 0, 1).mb_substr((string) $staff->last_name, 0, 1));
@endphp
<x-layouts.app title="Staff attendance">
    <x-ui.page-header title="Staff attendance" :description="($isToday ? 'Today · ' : '').$date->format('l, d M Y')"
                      :breadcrumbs="[['label' => 'Attendance', 'url' => route('school.attendance.index')], ['label' => 'Staff']]">
        <x-slot:actions>
            @can('attendance.reports.view')
                <x-ui.button :href="route('school.attendance.reports.staff', ['month' => $date->format('Y-m')])" variant="secondary" icon="chart-bar">Monthly summary</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('school.attendance.staff.index') }}" data-lock="false" class="att-datebar mb-5">
        @if ($previous)
            <x-ui.button :href="$dayUrl($previous)" variant="secondary" icon="chevron-left" class="btn-icon min-h-touch" aria-label="Previous school day ({{ $previous->format('d M') }})" />
        @endif
        <x-form.date name="date" label="Date" :value="$date->toDateString()" max="{{ $today->toDateString() }}" onchange="this.form.requestSubmit()" />
        @if ($next)
            <x-ui.button :href="$dayUrl($next)" variant="secondary" icon="chevron-right" class="btn-icon min-h-touch" aria-label="Next school day ({{ $next->format('d M') }})" />
        @endif
        <x-ui.button type="submit" variant="secondary" class="min-h-touch">Go</x-ui.button>
        @unless ($isToday)
            <x-ui.button :href="route('school.attendance.staff.index')" variant="ghost" class="min-h-touch">Today</x-ui.button>
        @endunless
    </form>

    @if ($non_working)
        <x-ui.alert tone="info" title="No school on this day" class="mb-5">{{ $non_working }}. Staff attendance is not taken.</x-ui.alert>
    @elseif ($marking_error && count($pendingRows) > 0 && $canMark)
        <x-ui.alert tone="warning" class="mb-5">{{ $marking_error }}</x-ui.alert>
    @endif

    @if (count($rows) === 0)
        <x-ui.card>
            <x-ui.empty icon="identification" title="No staff yet" description="Active staff members appear here once they are added to the staff register." />
        </x-ui.card>
    @else
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-ui.stat label="Marked" :value="$marked.' / '.count($rows)" icon="clipboard-document-check" tone="success" />
            <x-ui.stat label="Present" :value="($summary['present'] + $summary['late'])" icon="user" tone="brand" />
            <x-ui.stat label="Absent" :value="$summary['absent']" icon="user-minus" tone="danger" />
            <x-ui.stat label="On leave" :value="$summary['leave']" icon="calendar-days" tone="info" />
        </div>

        @if ($canSubmit)
            <div x-data="attendanceGrid(@js(['statuses' => collect($pendingRows)->mapWithKeys(fn ($r) => [(string) $r['staff']->id => 'present'])->all(), 'editing' => true]))" class="mb-6">
                <x-form.form :action="route('school.attendance.staff.store')" @submit="onSubmit()">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <x-ui.card :padding="false" :title="$marked > 0 ? 'Not marked yet' : 'Mark staff attendance'" :description="count($pendingRows).' staff member(s)'">
                        <x-slot:actions>
                            <x-ui.button variant="secondary" size="sm" icon="check-circle" @click="markAll('present')">Mark all present</x-ui.button>
                        </x-slot:actions>
                        <ul class="att-roster border-t border-line" aria-label="Staff to mark">
                            @foreach ($pendingRows as $row)
                                @include('school.attendance.partials.grid-row', [
                                    'key' => (string) $row['staff']->id,
                                    'field' => 'staff['.$row['staff']->id.']',
                                    'badge' => $initials($row['staff']),
                                    'name' => $row['staff']->fullName(),
                                    'sub' => $staffLine($row['staff']),
                                    'status' => 'present',
                                    'remark' => null,
                                    'locked' => false,
                                ])
                            @endforeach
                        </ul>
                    </x-ui.card>
                    @error('attendance')
                        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
                    @enderror
                    @error('date')
                        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
                    @enderror
                    <div class="att-submitbar flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-ink-soft" aria-live="polite"><strong class="text-ink" x-text="count('absent')">0</strong> absent · <strong class="text-ink" x-text="count('leave')">0</strong> on leave</p>
                        <x-ui.button type="submit" size="lg" icon="check" class="w-full sm:w-auto">Save staff attendance</x-ui.button>
                    </div>
                </x-form.form>
            </div>
        @elseif (count($pendingRows) > 0 && count($markedRows) === 0 && ! $non_working)
            <x-ui.card class="mb-6">
                <x-ui.empty icon="clock" title="Not marked" description="Staff attendance for this date has not been taken." />
            </x-ui.card>
        @endif

        @if (count($markedRows) > 0)
            <div x-data="attendanceGrid(@js(['statuses' => collect($markedRows)->mapWithKeys(fn ($r) => [(string) $r['record']->id => $r['record']->status->value])->all(), 'editing' => false, 'correction' => true]))">
                <x-form.form :action="route('school.attendance.staff.correct')" method="PUT" @submit="onSubmit()">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <x-ui.card :padding="false" title="Marked" :description="count($markedRows).' staff member(s)'">
                        @if ($canMark)
                            <x-slot:actions>
                                <x-ui.button variant="secondary" size="sm" icon="pencil-square" x-show="!editing" @click="startCorrection()">Correct</x-ui.button>
                            </x-slot:actions>
                        @endif
                        <ul class="att-roster border-t border-line" :class="{ 'is-readonly': !editing }" aria-label="Marked staff">
                            @foreach ($markedRows as $row)
                                @php $record = $row['record']; @endphp
                                @include('school.attendance.partials.grid-row', [
                                    'key' => (string) $record->id,
                                    'field' => 'records['.$record->id.']',
                                    'badge' => $initials($row['staff']),
                                    'name' => $row['staff']->fullName(),
                                    'sub' => $staffLine($row['staff']),
                                    'status' => $record->status->value,
                                    'remark' => $record->remark,
                                    'locked' => true,
                                    'meta' => $record->corrected_at
                                        ? 'Corrected by '.($record->corrector?->name ?? 'a former user').' on '.$record->corrected_at->timezone($tz)->format('d M, h:i A')
                                        : 'Marked by '.($record->marker?->name ?? 'a former user').($record->marked_at ? ' at '.$record->marked_at->timezone($tz)->format('h:i A') : ''),
                                ])
                            @endforeach
                        </ul>
                    </x-ui.card>

                    @if ($canMark)
                        <div class="att-submitbar space-y-3" x-show="editing" x-cloak>
                            <x-form.textarea name="reason" label="Reason for the correction" rows="2" required x-ref="reason" maxlength="500"
                                             hint="Recorded with your name and the time, for every changed record." />
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-ink-soft" aria-live="polite"><strong class="text-ink" x-text="changed">0</strong> record(s) changed</p>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                    <x-ui.button variant="ghost" @click="cancelCorrection()">Cancel</x-ui.button>
                                    <x-ui.button type="submit" icon="check" x-bind:disabled="changed === 0">Save correction</x-ui.button>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-form.form>
            </div>
        @endif

        @if ($corrections->isNotEmpty())
            <x-ui.card title="Correction history" :padding="false" class="mt-6">
                <x-ui.table caption="Staff attendance corrections" dense>
                    <x-slot:head>
                        <x-ui.th>Staff</x-ui.th>
                        <x-ui.th>Change</x-ui.th>
                        <x-ui.th>Reason</x-ui.th>
                        <x-ui.th>By</x-ui.th>
                    </x-slot:head>
                    @foreach ($corrections as $correction)
                        <tr>
                            <td class="font-medium text-ink">{{ $correction->staffProfile?->fullName() ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                <x-ui.badge :tone="$correction->old_status->tone()">{{ $correction->old_status->label() }}</x-ui.badge>
                                <x-ui.icon name="arrow-right" class="inline h-4 w-4 text-ink-faint" />
                                <x-ui.badge :tone="$correction->new_status->tone()">{{ $correction->new_status->label() }}</x-ui.badge>
                            </td>
                            <td class="text-ink-soft">{{ $correction->reason }}</td>
                            <td class="whitespace-nowrap text-sm text-ink-soft">{{ $correction->corrector?->name ?? '—' }}<br>{{ $correction->created_at->timezone($tz)->format('d M Y, h:i A') }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @endif
    @endif

    @error('reason')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror
    @error('records')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror
</x-layouts.app>
