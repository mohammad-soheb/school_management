{{--
    <x-form.input name="email" label="Email address" type="email" :value="$user->email" hint="We never share it." required />
    <x-form.input name="items[0][name]" label="Item" />                    bracket names work; errors read items.0.name
    <x-form.input name="weight" label="Weight" suffix="kg" inputmode="decimal" />

    * value is the default; old('name') (after a failed validation) wins. Passwords are never re-filled.
    * Extra attributes (placeholder, autocomplete, min, max, maxlength, pattern, wire/x-* ...) go to the <input>.
      `class` goes to the field wrapper (layout, e.g. md:col-span-2); use `input-class` for the control itself.
    * The control gets aria-invalid + aria-describedby (hint / error) and a stable id (f-{name}); pass id="" to override.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'prefix' => null, 'suffix' => null, 'inputClass' => ''])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $fieldId = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $current = $type === 'password' || $type === 'file' ? null : old($fieldKey, $value);
    $describedBy = trim(($hint && ! $error ? $fieldId.'-hint ' : '').($error ? $fieldId.'-error' : ''));
    $grouped = filled($prefix) || filled($suffix);

    $controlAttributes = $attributes->except(['class', 'id'])
        ->merge(array_filter([
            'type' => $type,
            'name' => $name,
            'id' => $fieldId,
            'value' => $current !== null && $current !== false ? (string) $current : null,
            'required' => $required ? true : null,
            'aria-required' => $required ? 'true' : null,
            'aria-invalid' => $error ? 'true' : null,
            'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        ], fn ($v) => $v !== null))
        ->class(['input', $inputClass => filled($inputClass)]);
@endphp
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)
        <label for="{{ $fieldId }}" @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    @if ($grouped)
        <div @class(['input-group', 'is-invalid' => $error])>
            @if (filled($prefix))<span class="input-affix">{{ $prefix }}</span>@endif
            <input {{ $controlAttributes }}>
            @if (filled($suffix))<span class="input-affix">{{ $suffix }}</span>@endif
        </div>
    @else
        <input {{ $controlAttributes }}>
    @endif

    @if ($hint && ! $error)
        <p class="hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$fieldId" />
</div>
