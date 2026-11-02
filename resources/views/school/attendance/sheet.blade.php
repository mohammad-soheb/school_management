@php
    /** @var \App\Models\SchoolSetup\Section $section */
    $label = $section->label();
    $dateLabel = $date->format('l, d M Y');
    $isToday = $date->isSameDay(\App\Services\Attendance\AttendanceCalendar::today());
    $statusCases = \App\Enums\Attendance\AttendanceStatus::cases();
    $tz = config('app.display_timezone');

    if ($sheet) {
        $initial = $records->mapWithKeys(fn ($r) => [(string) $r->id => $r->status->value])->all();
    } else {
        $initial = $roster ? $roster->mapWithKeys(fn ($e) => [(string) $e->id => 'present'])->all() : [];
    }
    $canSubmit = ! $sheet && $can_mark && $marking_error === null && $roster && $roster->isNotEmpty();
    $navUrl = fn ($day) => route('school.attendance.mark', [$section, 'date' => $day->toDateString()]);
@endphp
<x-layouts.app :title="'Attendance · '.$label">
    <x-ui.page-header :title="$label" :description="$dateLabel.($isToday ? ' · Today' : '')"
                      :breadcrumbs="[['label' => 'Attendance', 'url' => route('school.attendance.index', ['date' => $date->toDateString()])], ['label' => $label]]">
        @if ($sheet)
            <x-ui.badge tone="success" dot>Submitted</x-ui.badge>
        @elseif ($canSubmit)
            <x-ui.badge tone="warning" dot>Not marked yet</x-ui.badge>
        @endif
        <x-slot:actions>
            @if ($previous)
                <x-ui.button :href="$navUrl($previous)" variant="secondary" icon="chevron-left" aria-label="Previous school day">{{ $previous->format('d M') }}</x-ui.button>
            @endif
            @if ($next)
                <x-ui.button :href="$navUrl($next)" variant="secondary" icon-right="chevron-right" aria-label="Next school day">{{ $next->format('d M') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($sheet)
        <x-ui.alert tone="success" class="mb-4">
            Submitted by {{ $sheet->submitter?->name ?? 'a former user' }} on {{ $sheet->submitted_at?->timezone($tz)->format('d M Y, h:i A') }}.
            @if ($can_correct)
                To change a status, use <strong>Correct</strong> and give a reason; every change is recorded.
            @else
                It can only be changed by a correction from the school admin.
            @endif
        </x-ui.alert>
    @elseif ($marking_error)
        <x-ui.alert tone="warning" title="Attendance cannot be marked" class="mb-4">{{ $marking_error }}</x-ui.alert>
    @endif

    @if (! $sheet && (! $roster || $roster->isEmpty()))
        <x-ui.card>
            <x-ui.empty icon="user-group" title="No students in this class" description="Students enrolled in {{ $label }} for the current academic year appear here." />
        </x-ui.card>
    @elseif ($sheet || $canSubmit)
        <div x-data="attendanceGrid(@js(['statuses' => $initial, 'editing' => ! $sheet, 'correction' => (bool) $sheet]))">
            <x-form.form :action="$sheet ? route('school.attendance.sheets.correct', $sheet) : route('school.attendance.store', $section)"
                         :method="$sheet ? 'PUT' : 'POST'" @submit="onSubmit()">
                @unless ($sheet)
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                @endunless

                <x-ui.card :padding="false">
                    <div class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="att-counts" aria-live="polite">
                            <span class="att-count">Students <strong x-text="total">{{ count($initial) }}</strong></span>
                            @foreach ($statusCases as $case)
                                <span class="att-count">
                                    <span class="dot-{{ $case->tone() }}" aria-hidden="true"></span>{{ $case->label() }}
                                    <strong x-text="count('{{ $case->value }}')">{{ $sheet ? ($summary[$case->value] ?? 0) : ($case->value === 'present' ? count($initial) : 0) }}</strong>
                                </span>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="sr-only" for="att-filter">Find a student</label>
                            <input id="att-filter" type="search" x-model.debounce.150ms="filter" class="input max-w-[14rem]" placeholder="Find a student" autocomplete="off">
                            @if ($sheet)
                                @if ($can_correct)
                                    <x-ui.button variant="secondary" icon="pencil-square" x-show="!editing" @click="startCorrection()">Correct</x-ui.button>
                                    <x-ui.button variant="secondary" icon="check-circle" x-show="editing" x-cloak @click="markAll('present')">Mark all present</x-ui.button>
                                @endif
                            @else
                                <x-ui.button variant="secondary" icon="check-circle" @click="markAll('present')">Mark all present</x-ui.button>
                            @endif
                        </div>
                    </div>

                    <ul class="att-roster" @if ($sheet) :class="{ 'is-readonly': !editing }" @endif aria-label="Students">
                        @if ($sheet)
                            @foreach ($records as $record)
                                @include('school.attendance.partials.grid-row', [
                                    'key' => (string) $record->id,
                                    'field' => 'records['.$record->id.']',
                                    'badge' => $record->enrollment->roll_no ?? '–',
                                    'name' => $record->student->fullName(),
                                    'sub' => $record->student->admission_no,
                                    'status' => $record->status->value,
                                    'remark' => $record->remark,
                                    'locked' => true,
                                    'meta' => $record->corrected_at ? 'Corrected by '.($record->corrector?->name ?? 'a former user').' on '.$record->corrected_at->timezone($tz)->format('d M, h:i A') : null,
                                ])
                            @endforeach
                        @else
                            @foreach ($roster as $enrollment)
                                @include('school.attendance.partials.grid-row', [
                                    'key' => (string) $enrollment->id,
                                    'field' => 'attendance['.$enrollment->id.']',
                                    'badge' => $enrollment->roll_no ?? '–',
                                    'name' => $enrollment->student->fullName(),
                                    'sub' => $enrollment->student->admission_no,
                                    'status' => 'present',
                                    'remark' => null,
                                    'locked' => false,
                                ])
                            @endforeach
                        @endif
                    </ul>
                </x-ui.card>

                @error('attendance')
                    <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
                @enderror
                @error('date')
                    <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
                @enderror

                @if ($sheet && $can_correct)
                    <div class="att-submitbar space-y-3" x-show="editing" x-cloak>
                        <x-form.textarea name="reason" label="Reason for the correction" rows="2" required x-ref="reason" maxlength="500"
                                         hint="Recorded with your name and the time, for every changed student." />
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-ink-soft" aria-live="polite"><strong class="text-ink" x-text="changed">0</strong> student(s) changed</p>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <x-ui.button variant="ghost" @click="cancelCorrection()">Cancel</x-ui.button>
                                <x-ui.button type="submit" icon="check" x-bind:disabled="changed === 0">Save correction</x-ui.button>
                            </div>
                        </div>
                    </div>
                @elseif (! $sheet)
                    <div class="att-submitbar flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-ink-soft" aria-live="polite">
                            <strong class="text-ink" x-text="count('present') + count('late') + count('half_day')">{{ count($initial) }}</strong> in class ·
                            <strong class="text-ink" x-text="count('absent')">0</strong> absent
                        </p>
                        <x-ui.button type="submit" size="lg" icon="check" class="w-full sm:w-auto">Save attendance</x-ui.button>
                    </div>
                @endif
            </x-form.form>
        </div>
    @else
        <x-ui.card>
            <x-ui.empty icon="calendar-days" title="Nothing to mark" description="Choose another date from the Attendance page.">
                <x-ui.button :href="route('school.attendance.index')" variant="secondary">Back to attendance</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @endif

    @if ($corrections->isNotEmpty())
        <x-ui.card title="Correction history" description="Every change to this register, newest first." :padding="false" class="mt-6">
            <x-ui.table caption="Correction history" dense>
                <x-slot:head>
                    <x-ui.th>Student</x-ui.th>
                    <x-ui.th>Change</x-ui.th>
                    <x-ui.th>Reason</x-ui.th>
                    <x-ui.th>By</x-ui.th>
                </x-slot:head>
                @foreach ($corrections as $correction)
                    <tr>
                        <td class="font-medium text-ink">{{ $correction->student?->fullName() ?? '—' }}</td>
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
</x-layouts.app>
