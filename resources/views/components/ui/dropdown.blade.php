{{--
    <x-ui.dropdown align="right" width="56">
        <x-slot:trigger>
            <button type="button" class="btn btn-secondary btn-sm">Actions</button>
        </x-slot:trigger>

        <x-ui.dropdown-link href="..." icon="pencil-square">Edit</x-ui.dropdown-link>
        <hr class="dropdown-divider">
        <form method="POST" action="..."> @csrf
            <x-ui.dropdown-link as="button" danger icon="trash">Delete</x-ui.dropdown-link>
        </form>
    </x-ui.dropdown>

    align: left | right (which edge of the trigger the panel lines up with). width: 48 | 56 | 64 | 72 | 80 (Tailwind w-*).
    The first <button>/<a> in the trigger slot is the menu button; aria-expanded / aria-haspopup are set for you.
--}}
@props(['align' => 'right', 'width' => '56', 'label' => null])
@php
    $widths = ['48' => 'w-48', '56' => 'w-56', '64' => 'w-64', '72' => 'w-72', '80' => 'w-80'];
    $alignment = $align === 'left' ? 'is-left' : 'is-right';
@endphp
<div {{ $attributes->class(['dropdown']) }} x-data="dropdown" @click.outside="close(false)" @keydown.escape.stop="close()" @keydown.tab="close(false)">
    <div x-ref="trigger" @click="toggle()" @keydown.arrow-down.prevent="openMenu()">{{ $trigger ?? '' }}</div>

    <div x-ref="panel" x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="dropdown-panel {{ $alignment }} {{ $widths[$width] ?? $widths['56'] }}"
         role="menu" @if ($label) aria-label="{{ $label }}" @endif
         @click="close(false)"
         @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
         @keydown.home.prevent="edge(false)" @keydown.end.prevent="edge(true)">
        {{ $slot }}
    </div>
</div>
