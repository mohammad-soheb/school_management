{{--
    Standalone validation message for a field name (dotted or bracket syntax).
    <x-form.error name="items[0][name]" />
    Renders nothing when the field has no error. The id is "{field id}-error" so a control can point aria-describedby at it.
--}}
@props(['name', 'id' => null])
@php
    $message = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $errorId = ($id ?? \App\View\Components\Support\Field::id($name)).'-error';
@endphp
@if ($message)
    <p id="{{ $errorId }}" {{ $attributes->class(['field-error']) }}>
        <x-ui.icon name="exclamation-circle" variant="s" />
        <span>{{ $message }}</span>
    </p>
@endif
