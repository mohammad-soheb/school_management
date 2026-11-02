{{--
    Menu item for <x-ui.dropdown>.
    <x-ui.dropdown-link href="..." icon="pencil-square">Edit</x-ui.dropdown-link>
    <x-ui.dropdown-link as="button" danger icon="trash">Delete</x-ui.dropdown-link>   <button type="submit">, put it inside a <form>
--}}
@props(['href' => null, 'icon' => null, 'danger' => false, 'as' => 'a'])
@php
    $classes = ['dropdown-item', 'dropdown-item-danger' => $danger];
@endphp
@if ($as === 'button')
    <button type="{{ $attributes->get('type', 'submit') }}" role="menuitem" {{ $attributes->except('type')->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@else
    <a href="{{ $href ?? '#' }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@endif
