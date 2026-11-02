{{--
    <x-form.textarea name="remarks" label="Remarks" :value="$payment->remarks" rows="4" hint="Printed on the receipt." />
    The value comes from old('name') first, then `value`. `class` goes to the wrapper, other attributes to the <textarea>.
--}}
@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4, 'inputClass' => ''])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $fieldId = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $current = old($fieldKey, $value);
    $describedBy = trim(($hint && ! $error ? $fieldId.'-hint ' : '').($error ? $fieldId.'-error' : ''));

    $controlAttributes = $attributes->except(['class', 'id'])
        ->merge(array_filter([
            'name' => $name,
            'id' => $fieldId,
            'rows' => $rows,
            'required' => $required ? true : null,
            'aria-required' => $required ? 'true' : null,
            'aria-invalid' => $error ? 'true' : null,
            'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        ], fn ($v) => $v !== null))
        ->class(['textarea', $inputClass => filled($inputClass)]);
@endphp
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)
        <label for="{{ $fieldId }}" @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    <textarea {{ $controlAttributes }}>{{ $current }}</textarea>

    @if ($hint && ! $error)
        <p class="hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$fieldId" />
</div>
