{{--
    Heroicon wrapper with a safe fallback.

    <x-ui.icon name="home" class="h-5 w-5" />              outline 24px (default)
    <x-ui.icon name="check-circle" variant="s" />           solid 24px   (o = outline, s = solid, m = mini 20px, c = micro 16px)
    <x-ui.icon name="heroicon-o-home" />                    a full icon name is used as is

    An unknown name renders the "squares-2x2" icon instead of throwing, so a typo in a menu fragment
    can never break a page. Icons are decorative (aria-hidden) unless you pass aria-label / title.
--}}
@props(['name' => 'squares-2x2', 'variant' => 'o'])
@php
    $variant = in_array($variant, ['o', 's', 'm', 'c'], true) ? $variant : 'o';
    $iconName = (string) $name;
    $iconName = str_starts_with($iconName, 'heroicon-') ? $iconName : "heroicon-{$variant}-{$iconName}";
    $iconClass = (string) $attributes->get('class', '');
    $iconAttributes = $attributes->except('class')->getAttributes();

    if (! array_key_exists('aria-label', $iconAttributes) && ! array_key_exists('title', $iconAttributes)) {
        $iconAttributes = ['aria-hidden' => 'true', 'focusable' => 'false'] + $iconAttributes;
    }

    try {
        $svg = svg($iconName, $iconClass, $iconAttributes);
    } catch (\Throwable) {
        $svg = svg('heroicon-o-squares-2x2', $iconClass, $iconAttributes);
    }
@endphp
{{ $svg }}
