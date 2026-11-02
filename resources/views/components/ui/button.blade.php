{{--
    <x-ui.button variant="primary|secondary|ghost|danger|success" size="sm|md|lg" icon="plus" block>Add student</x-ui.button>
    <x-ui.button href="{{ route('...') }}" variant="secondary">Back</x-ui.button>      renders <a class="btn ...">
    <x-ui.button type="submit">Save</x-ui.button>

    Extra attributes (wire/x-/aria-/name/value ...) are merged onto the element.
    Icon-only button:  <x-ui.button variant="ghost" icon="trash" class="btn-icon" aria-label="Delete" />
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconRight' => null,
    'block' => false,
])
@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
        'success' => 'btn-success',
    ];
    $sizes = ['sm' => 'btn-sm', 'md' => 'btn-md', 'lg' => 'btn-lg'];
    $classes = ['btn', $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md'], 'btn-block' => $block];
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>
    @if ($icon)<x-ui.icon :name="$icon" />@endif
    {{ $slot }}
    @if ($iconRight)<x-ui.icon :name="$iconRight" />@endif
</a>
@else
<button type="{{ $type }}" {{ $attributes->class($classes) }}>
    @if ($icon)<x-ui.icon :name="$icon" />@endif
    {{ $slot }}
    @if ($iconRight)<x-ui.icon :name="$iconRight" />@endif
</button>
@endif
