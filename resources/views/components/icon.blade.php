{{--
    <x-icon name="home" class="h-5 w-5" variant="s" />

    Thin alias of <x-ui.icon>. NOTE: blade-ui-kit/blade-icons registers its own <x-icon name="heroicon-o-home"> alias
    (config blade-icons.components.default = 'icon'), and Blade resolves registered aliases BEFORE anonymous
    components, so while that default is enabled this file is never reached. Use <x-ui.icon> everywhere; it behaves
    identically and also accepts bare names. See docs/UI_GUIDE.md ("Icons").
--}}
@props(['name' => 'squares-2x2', 'variant' => 'o'])
<x-ui.icon :name="$name" :variant="$variant" {{ $attributes }} />
