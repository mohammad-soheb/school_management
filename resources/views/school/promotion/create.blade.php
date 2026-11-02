@php
    $setupYearsUrl = \Illuminate\Support\Facades\Route::has('school.setup.years.index') ? route('school.setup.years.index') : null;
    $oldClasses = array_map('intval', (array) old('class_ids', $classes->reject(fn ($c) => $c['inDraft'])->map(fn ($c) => $c['class']->id)->all()));
@endphp
<x-layouts.app title="New promotion run">
    <x-ui.page-header title="New promotion run"
                      description="Step 1 of 3: choose the years and classes. You will review every student before anything changes."
                      :breadcrumbs="[['label' => 'Year-end promotion', 'url' => route('school.promotion.index')], ['label' => 'New run']]" />

    @if ($errors->any())
        <x-ui.alert tone="danger" title="Please check the form" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($from === null)
        <x-ui.card>
            <x-ui.empty icon="calendar-days" title="No academic years yet" description="Create the current academic year in school setup before promoting students.">
                @if ($setupYearsUrl)
                    <x-ui.button :href="$setupYearsUrl" icon="plus">Set up academic years</x-ui.button>
                @endif
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="space-y-6">
            <x-ui.card title="From year" description="Students' active enrollments in this year will be closed.">
                <x-form.form method="GET" :action="route('school.promotion.create')" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-lock="false">
                    <x-form.select name="from" label="Source year" class="sm:w-64" :selected="$from->id"
                                   :options="$years->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->is_current ? ' (current)' : '').($y->isLocked() ? ' - locked' : '')])->all()" />
                    <x-ui.button type="submit" variant="secondary" icon="arrow-path">Change</x-ui.button>
                </x-form.form>
                @if ($from->isLocked())
                    <x-ui.alert tone="warning" class="mt-4">{{ $from->name }} is locked. Reopen it from the promotion page before running a promotion from it.</x-ui.alert>
                @endif
            </x-ui.card>

            @if ($targets->isEmpty())
                <x-ui.alert tone="warning" title="No target year">
                    There is no academic year after {{ $from->name }}. Create next year in school setup first (it does not have to be the current year yet).
                    @if ($setupYearsUrl)
                        <div class="mt-3"><x-ui.button :href="$setupYearsUrl" size="sm" icon="plus">Create next year</x-ui.button></div>
                    @endif
                </x-ui.alert>
            @elseif ($classes->isEmpty())
                <x-ui.card>
                    <x-ui.empty icon="users" title="No active students in {{ $from->name }}"
                                description="Every student of this year has already been promoted or has left. Pick another source year." />
                </x-ui.card>
            @else
                <x-form.form :action="route('school.promotion.store')" class="space-y-6">
                    <x-form.idempotency />
                    <input type="hidden" name="from_academic_year_id" value="{{ $from->id }}">

                    <x-ui.card title="To year" description="New enrollments are created in this year. It must be open.">
                        <x-form.select name="to_academic_year_id" label="Target year" class="sm:w-64" required :selected="$suggestedTo?->id"
                                       :options="$targets->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->isLocked() ? ' - locked' : '')])->all()" />
                    </x-ui.card>

                    <x-ui.card title="Classes" description="Default outcome: promote to the next class; classes without a next class graduate. You can change any student in the next step."
                               x-data="{ all: true }">
                        <x-slot:actions>
                            <button type="button" class="rounded text-sm font-medium text-brand-ink underline-offset-4 hover:underline"
                                    @click="all = !all; $el.closest('section').querySelectorAll('input[name=\'class_ids[]\']:not(:disabled)').forEach(c => c.checked = all)"
                                    x-text="all ? 'Clear all' : 'Select all'"></button>
                        </x-slot:actions>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($classes as $row)
                                <label @class(['flex min-h-[3.25rem] items-start gap-3 rounded-lg border border-line p-3', 'opacity-60' => $row['inDraft'], 'cursor-pointer hover:bg-surface-muted' => ! $row['inDraft']])>
                                    <input type="checkbox" name="class_ids[]" value="{{ $row['class']->id }}" class="check-input mt-0.5"
                                           @checked(in_array($row['class']->id, $oldClasses, true)) @disabled($row['inDraft'])>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-ink">{{ $row['class']->name }}</span>
                                        <span class="block text-sm text-ink-soft">
                                            {{ $row['students'] }} {{ \Illuminate\Support\Str::plural('student', $row['students']) }} &middot;
                                            @if ($row['next']) promote to {{ $row['next'] }} @else <span class="text-brand-ink">graduates</span> @endif
                                        </span>
                                        @if ($row['inDraft'])
                                            <a href="{{ route('school.promotion.show', $row['inDraft']) }}" class="mt-1 block text-sm text-brand-ink underline-offset-4 hover:underline">In draft run #{{ $row['inDraft'] }}</a>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-form.error name="class_ids" />
                    </x-ui.card>

                    <x-ui.card title="Options">
                        <div class="space-y-1">
                            <x-form.toggle name="carry_forward_dues" label="Carry forward unpaid dues"
                                           hint="Unpaid fees of the old year become an opening balance in the new year, linked to the original charges. Old invoices are never rewritten."
                                           :checked="old('carry_forward_dues', true)" />
                            <x-form.toggle name="lock_source_year" label="Lock the source year after commit"
                                           hint="Makes the old year read-only. Skipped while it is still the current year; you can lock it later from the run page."
                                           :checked="old('lock_source_year', false)" />
                        </div>
                    </x-ui.card>

                    <x-form.actions>
                        <x-ui.button :href="route('school.promotion.index')" variant="secondary">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon-right="arrow-right">Prepare preview</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            @endif
        </div>
    @endif
</x-layouts.app>
