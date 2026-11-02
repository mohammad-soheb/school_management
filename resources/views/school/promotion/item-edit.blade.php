@php
    $student = $item->student;
    $yearsLocked = $run->fromYear->isLocked() || $run->toYear->isLocked();
@endphp
<x-layouts.app :title="'Correct · '.$student->fullName()">
    <x-ui.page-header :title="'Correct '.$student->fullName()"
                      :description="'Promotion run #'.$run->id.': '.$run->fromYear->name.' → '.$run->toYear->name"
                      :breadcrumbs="[['label' => 'Year-end promotion', 'url' => route('school.promotion.index')], ['label' => 'Run #'.$run->id, 'url' => route('school.promotion.show', [$run, 'class' => $item->from_class_id])], ['label' => 'Correct']]" />

    @if ($errors->any())
        <x-ui.alert tone="danger" title="Not saved" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($yearsLocked)
        <x-ui.alert tone="warning" title="A year is locked" class="mb-6">
            {{ $run->fromYear->isLocked() ? $run->fromYear->name : $run->toYear->name }} is locked. A user with the reopen permission must reopen it (with a reason) from the
            <a href="{{ route('school.promotion.index') }}" class="font-medium underline">promotion page</a> before this student can be corrected.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Current result">
            <x-ui.dl>
                <x-ui.dl-item label="Admission no.">{{ $student->admission_no }}</x-ui.dl-item>
                <x-ui.dl-item :label="$run->fromYear->name">{{ $item->fromClass->name }}{{ $item->enrollment->section ? ' - '.$item->enrollment->section->name : '' }}</x-ui.dl-item>
                <x-ui.dl-item label="Outcome"><x-ui.badge :tone="$item->outcome->tone()">{{ $item->outcome->label() }}</x-ui.badge></x-ui.dl-item>
                <x-ui.dl-item :label="$run->toYear->name">
                    @if ($item->targetClass){{ $item->targetClass->name }}{{ $item->targetSection ? ' - '.$item->targetSection->name : '' }}{{ $item->target_roll_no ? ', roll '.$item->target_roll_no : '' }}@else Leaves school @endif
                </x-ui.dl-item>
            </x-ui.dl>
        </x-ui.card>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Change the outcome or placement"
                       description="Moving or cancelling the new enrollment is refused once attendance, fees or marks are recorded against it.">
                <x-form.form :action="route('school.promotion.items.correct', [$run, $item])" method="PUT" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.select name="outcome" label="Outcome" :options="$outcomes" :selected="$item->outcome->value" required />
                        <x-form.select name="target_section_id" label="Section next year" placeholder="Default section" :selected="$item->target_section_id">
                            @foreach ($sectionOptions as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $sectionId => $sectionName)
                                        <option value="{{ $sectionId }}" @selected((string) old('target_section_id', $item->target_section_id) === (string) $sectionId)>{{ $sectionName }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </x-form.select>
                        <x-form.input name="target_roll_no" label="Roll no. next year" :value="$item->target_roll_no" maxlength="10" />
                    </div>
                    <x-form.textarea name="reason" label="Reason" rows="2" required maxlength="255" id="correct-reason"
                                     hint="Kept in the audit log, for example: parents asked for section B." />
                    <x-form.actions>
                        <x-ui.button :href="route('school.promotion.show', [$run, 'class' => $item->from_class_id])" variant="secondary">Back</x-ui.button>
                        <x-ui.button type="submit" icon="check" :disabled="$yearsLocked">Save correction</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            </x-ui.card>

            <x-ui.card title="Revert this student" description="Undo the promotion: the {{ $run->fromYear->name }} enrollment becomes active again and the student gets no place in {{ $run->toYear->name }} from this run.">
                <x-form.form :action="route('school.promotion.items.revert', [$run, $item])" class="space-y-4">
                    <x-form.textarea name="reason" label="Reason for reverting" rows="2" required maxlength="255" id="revert-reason" />
                    <x-form.actions>
                        <x-ui.button type="submit" variant="danger" icon="arrow-uturn-left" :disabled="$yearsLocked">Revert promotion</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
