{{--
    <x-form.select name="class_id" label="Class" :options="[1 => 'Class 1', 2 => 'Class 2']" :selected="$student->class_id" placeholder="Choose a class" required />
    <x-form.select name="roles[]" label="Roles" :options="$roles" :selected="$user->roleIds()" multiple />
    <x-form.select name="section" label="Section"><option value="A">A</option>...</x-form.select>      raw options / <optgroup>s

    * options: value => label. The selected value comes from old('name') first, then `selected`.
    * placeholder adds an empty first option (value="").
    * `class` goes to the wrapper, other attributes to the <select>; `input-class` adds classes to the <select>.
--}}
@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'hint' => null, 'required' => false, 'inputClass' => ''])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $fieldId = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    // A multi-select with nothing chosen submits no key at all, so after a failed validation an absent key means
    // "none", not "fall back to the default".
    $isMultiple = $attributes->has('multiple') && $attributes->get('multiple') !== false;
    $current = $isMultiple && \App\View\Components\Support\Field::hasOldInput()
        ? old($fieldKey, [])
        : old($fieldKey, $selected);
    $chosen = array_map('strval', \Illuminate\Support\Arr::wrap($current));
    $describedBy = trim(($hint && ! $error ? $fieldId.'-hint ' : '').($error ? $fieldId.'-error' : ''));

    $controlAttributes = $attributes->except(['class', 'id'])
        ->merge(array_filter([
            'name' => $name,
            'id' => $fieldId,
            'required' => $required ? true : null,
            'aria-required' => $required ? 'true' : null,
            'aria-invalid' => $error ? 'true' : null,
            'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        ], fn ($v) => $v !== null))
        ->class(['select', $inputClass => filled($inputClass)]);
@endphp
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)
        <label for="{{ $fieldId }}" @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    <select {{ $controlAttributes }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $chosen, true))>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>

    @if ($hint && ! $error)
        <p class="hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$fieldId" />
</div>
