{{--
    Label + control + hint + first validation error, around ANY control you put in the slot.

    <x-form.field label="Photo" name="photo" hint="JPG or PNG, max 2 MB" required>
        <input type="file" name="photo" id="f-photo" class="input" aria-describedby="f-photo-hint f-photo-error">
    </x-form.field>

    The label points at id="f-{name}" (name sanitised: items[0][name] -> f-items-0-name); give the control that id, or pass `for`.
    The hint has id "{id}-hint" and the error "{id}-error" for aria-describedby.
    The ready-made controls (form.input, form.select ...) already do all of this; use form.field for custom ones.
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'for' => null])
@php
    $fieldId = $for ?? ($name ? \App\View\Components\Support\Field::id($name) : null);
    $error = $name ? \App\View\Components\Support\Field::error($errors ?? null, $name) : null;
@endphp
<div {{ $attributes->class(['field']) }}>
    @if ($label)
        <label @if ($fieldId) for="{{ $fieldId }}" @endif @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint && ! $error)
        <p class="hint" @if ($fieldId) id="{{ $fieldId }}-hint" @endif>{{ $hint }}</p>
    @endif
    @if ($name)
        <x-form.error :name="$name" :id="$fieldId" />
    @endif
</div>
