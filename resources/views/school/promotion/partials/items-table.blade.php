{{--
    Students of one class in a promotion run.
    editable: draft rows get selects bound to #items-form and a checkbox bound to #bulk-form.
--}}
@if ($items->isEmpty())
    <x-ui.empty icon="users" title="No students in this class" />
@else
    <x-ui.table caption="Students" dense x-data="{ all: false }">
        <x-slot:head>
            @if ($editable)
                <th class="w-10">
                    <input type="checkbox" class="check-input" aria-label="Select all students" x-model="all"
                           @change="document.querySelectorAll('input[form=bulk-form][name=\'item_ids[]\']').forEach(c => c.checked = all)">
                </th>
            @endif
            <x-ui.th>Student</x-ui.th>
            <x-ui.th>Now</x-ui.th>
            <x-ui.th>Outcome</x-ui.th>
            <x-ui.th>Next year</x-ui.th>
            <x-ui.th>Roll no.</x-ui.th>
            <x-ui.th>{{ $editable ? 'Note' : 'Status' }}</x-ui.th>
            @if (! $editable && ($canCorrect ?? false))
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            @endif
        </x-slot:head>

        @foreach ($items as $item)
            @php
                $editableRow = $editable && $item->isPending();
                $name = "items[{$item->id}]";
                $errorKey = "items.{$item->id}.outcome";
            @endphp
            <tr @class(['opacity-70' => ! $item->isPending() && $editable])>
                @if ($editable)
                    <td>
                        @if ($editableRow)
                            <input type="checkbox" form="bulk-form" name="item_ids[]" value="{{ $item->id }}" class="check-input"
                                   aria-label="Select {{ $item->student->fullName() }}">
                        @endif
                    </td>
                @endif
                <td>
                    <p class="font-medium text-ink">{{ $item->student->fullName() }}</p>
                    <p class="text-xs text-ink-faint">{{ $item->student->admission_no }}</p>
                    @foreach ($flags[$item->id] ?? [] as $flag)
                        <p class="mt-1 flex items-center gap-1 text-xs text-warning-ink"><x-ui.icon name="exclamation-triangle" variant="m" class="h-4 w-4" />{{ $flag }}</p>
                    @endforeach
                </td>
                <td class="whitespace-nowrap text-sm text-ink-soft">
                    {{ $item->fromClass->name }}{{ $item->enrollment->section ? ' - '.$item->enrollment->section->name : '' }}
                    @if ($item->enrollment->roll_no)<span class="text-ink-faint">&middot; {{ $item->enrollment->roll_no }}</span>@endif
                </td>
                @if ($editableRow)
                    <td>
                        <select name="{{ $name }}[outcome]" class="select min-w-[8.5rem]" aria-label="Outcome for {{ $item->student->fullName() }}"
                                @error($errorKey) aria-invalid="true" @enderror>
                            @foreach ($outcomes as $value => $label)
                                <option value="{{ $value }}" @selected(old("items.{$item->id}.outcome", $item->outcome->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error($errorKey)<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </td>
                    <td>
                        <select name="{{ $name }}[target_section_id]" class="select min-w-[9rem]" aria-label="Section next year for {{ $item->student->fullName() }}">
                            <option value="">Default section</option>
                            @foreach ($sectionOptions as $group => $options)
                                <optgroup label="{{ $group }}">
                                    @foreach ($options as $sectionId => $sectionName)
                                        <option value="{{ $sectionId }}" @selected((string) old("items.{$item->id}.target_section_id", $item->target_section_id) === (string) $sectionId)>{{ $sectionName }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-faint">{{ $item->targetClass?->name ?? 'Leaves school' }}</p>
                    </td>
                    <td>
                        <input type="text" name="{{ $name }}[target_roll_no]" value="{{ old("items.{$item->id}.target_roll_no", $item->target_roll_no) }}"
                               class="input w-20" maxlength="10" inputmode="numeric" aria-label="Roll no. next year for {{ $item->student->fullName() }}">
                    </td>
                    <td>
                        <input type="text" name="{{ $name }}[note]" value="{{ old("items.{$item->id}.note", $item->note) }}"
                               class="input min-w-[10rem]" maxlength="255" placeholder="Optional" aria-label="Note for {{ $item->student->fullName() }}">
                    </td>
                @else
                    <td><x-ui.badge :tone="$item->outcome->tone()">{{ $item->outcome->label() }}</x-ui.badge></td>
                    <td class="whitespace-nowrap text-sm">
                        @if ($item->targetClass)
                            {{ $item->targetClass->name }}{{ $item->targetSection ? ' - '.$item->targetSection->name : '' }}
                        @else
                            <span class="text-ink-faint">Leaves school</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $item->target_roll_no ?? '—' }}</td>
                    <td class="text-sm">
                        @if ($editable)
                            <x-ui.badge :tone="$item->state->tone()">{{ \App\Enums\Promotion\PromotionItemState::skipReasonLabel($item->skip_reason) }}</x-ui.badge>
                        @else
                            <x-ui.badge :tone="$item->state->tone()" dot>{{ $item->state->label() }}</x-ui.badge>
                            @if ($item->skip_reason)
                                <p class="mt-1 text-xs text-ink-faint">{{ \App\Enums\Promotion\PromotionItemState::skipReasonLabel($item->skip_reason) }}</p>
                            @endif
                            @if ($item->dues_carried_paise > 0)
                                <p class="mt-1 text-xs text-ink-faint">Dues carried: @money($item->dues_carried_paise)</p>
                            @endif
                            @if ($item->corrected_at)
                                <p class="mt-1 text-xs text-ink-faint">Corrected {{ $item->corrected_at->diffForHumans() }}</p>
                            @endif
                        @endif
                    </td>
                    @if (! $editable && ($canCorrect ?? false))
                        <td class="col-actions">
                            @if ($item->isApplied())
                                <x-ui.button :href="route('school.promotion.items.edit', [$run, $item])" variant="ghost" size="sm" icon="pencil-square">Correct</x-ui.button>
                            @endif
                        </td>
                    @endif
                @endif
            </tr>
        @endforeach
    </x-ui.table>
@endif
