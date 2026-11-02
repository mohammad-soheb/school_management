{{--
    <fieldset>
        <legend class="label">Gender</legend>
        <x-form.radio name="gender" value="female" label="Female" :checked="$student->gender === 'female'" />
        <x-form.radio name="gender" value="male" label="Male" :checked="$student->gender === 'male'" />
        <x-form.error name="gender" />
    </fieldset>

    Group radios by giving them the same name. old('name') wins over `checked` after a failed validation.
    A radio does not print the group's error itself (it would repeat once per option): add one <x-form.error name="..."/>
    after the group. Each radio is marked aria-invalid and points at that message (id "f-{name}-error").
--}}
@props(['name', 'value', 'label' => null, 'checked' => false, 'hint' => null])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $fieldId = \App\View\Components\Support\Field::id($name.'-'.$value, $attributes->get('id'));
    $groupErrorId = \App\View\Components\Support\Field::id($name).'-error';
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $isChecked = old($fieldKey) !== null ? (string) old($fieldKey) === (string) $value : (bool) $checked;
@endphp
<div {{ $attributes->only('class')->class(['field']) }}>
    <label for="{{ $fieldId }}" class="check">
        <input type="radio" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $value }}" {{ $attributes->except(['class', 'id'])->class(['check-input']) }}
               @checked($isChecked) @if ($error) aria-invalid="true" aria-describedby="{{ $groupErrorId }}" @endif>
        @if ($label || $hint || $slot->isNotEmpty())
            <span class="min-w-0">
                <span class="check-label">{{ $label }}{{ $slot }}</span>
                @if ($hint)<span class="check-hint">{{ $hint }}</span>@endif
            </span>
        @endif
    </label>
</div>
