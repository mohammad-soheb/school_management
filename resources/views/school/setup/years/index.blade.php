@php
    $canManage = auth()->user()->can('setup.years.manage');
    $canLock = auth()->user()->can('setup.years.lock');
@endphp
<x-layouts.app title="Academic years">
    <x-ui.page-header title="Academic years" description="Exactly one year is current: attendance, fees and exams work inside it. Locked years are read-only."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Academic years']]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button :href="route('school.setup.years.create')" icon="plus">New academic year</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @include('school.setup._nav')

    @error('year')
        <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card :padding="false">
        @if ($years->isEmpty())
            <x-ui.empty icon="calendar" title="No academic years yet" description="Create the current year (for example 2026-27, 1 April to 31 March) to get started.">
                @if ($canManage)
                    <x-ui.button :href="route('school.setup.years.create')" icon="plus">New academic year</x-ui.button>
                @endif
            </x-ui.empty>
        @else
            <x-ui.table caption="Academic years">
                <x-slot:head>
                    <x-ui.th>Year</x-ui.th>
                    <x-ui.th>Dates</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    @if ($canManage || $canLock)<th class="col-actions"><span class="sr-only">Actions</span></th>@endif
                </x-slot:head>

                @foreach ($years as $year)
                    <tr>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-ink">{{ $year->name }}</span>
                                @if ($year->is_current)
                                    <x-ui.badge tone="brand" dot>Current</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $year->starts_on->format('d M Y') }} – {{ $year->ends_on->format('d M Y') }}</td>
                        <td>
                            <x-ui.badge :tone="$year->status->tone()">{{ $year->status->label() }}</x-ui.badge>
                            @if ($year->isLocked() && $year->locked_at)
                                <p class="mt-1 text-xs text-ink-faint">
                                    {{ $year->locked_at->timezone(config('app.display_timezone'))->format('d M Y') }}@if ($year->lockedBy) · {{ $year->lockedBy->name }}@endif
                                </p>
                            @endif
                        </td>
                        @if ($canManage || $canLock)
                            <td class="col-actions">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($canManage && ! $year->isLocked())
                                        <x-ui.button :href="route('school.setup.years.edit', $year)" variant="ghost" size="sm" icon="pencil-square">Edit</x-ui.button>
                                        @if (! $year->is_current)
                                            <x-ui.confirm :action="route('school.setup.years.current', $year)" method="POST"
                                                          :title="'Make '.$year->name.' the current year?'"
                                                          message="Attendance, fees and exams will work in this year for everyone in the school."
                                                          confirm="Make current" tone="brand">
                                                <x-ui.button variant="ghost" size="sm" icon="star">Make current</x-ui.button>
                                            </x-ui.confirm>
                                        @endif
                                    @endif
                                    @if ($canLock)
                                        @if ($year->isLocked())
                                            <x-ui.button variant="ghost" size="sm" icon="lock-open" @click="$dispatch('open-modal', 'reopen-{{ $year->id }}')">Reopen</x-ui.button>
                                        @elseif (! $year->is_current)
                                            <x-ui.button variant="ghost" size="sm" icon="lock-closed" @click="$dispatch('open-modal', 'lock-{{ $year->id }}')">Lock</x-ui.button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <p class="mt-4 text-sm text-ink-faint">Lock a finished year once its results and fees are final. The current year cannot be locked.</p>

    @if ($canLock)
        @foreach ($years as $year)
            @php $action = $year->isLocked() ? 'reopen' : 'lock'; @endphp
            @continue($action === 'lock' && $year->is_current)
            <x-ui.modal :name="$action.'-'.$year->id" :title="($action === 'lock' ? 'Lock ' : 'Reopen ').$year->name"
                        :description="$action === 'lock' ? 'Its data becomes read-only until someone reopens it.' : 'Its data can be changed again. Lock it again when you are done.'"
                        :show="$errors->has('reason') && (int) old('year_id') === $year->id">
                <x-form.form :action="route('school.setup.years.'.$action, $year)" :id="$action.'-form-'.$year->id">
                    <input type="hidden" name="year_id" value="{{ $year->id }}">
                    <x-form.textarea name="reason" label="Reason" rows="3" required maxlength="500" hint="Recorded in the audit log." />
                </x-form.form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" @click="$dispatch('close-modal', '{{ $action }}-{{ $year->id }}')">Cancel</x-ui.button>
                    <x-ui.button type="submit" :form="$action.'-form-'.$year->id" :icon="$action === 'lock' ? 'lock-closed' : 'lock-open'" :variant="$action === 'lock' ? 'danger' : 'primary'">
                        {{ $action === 'lock' ? 'Lock year' : 'Reopen year' }}
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach
    @endif
</x-layouts.app>
