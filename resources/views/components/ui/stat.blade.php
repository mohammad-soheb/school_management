{{--
    <x-ui.stat label="Students" value="1,248" icon="academic-cap" tone="brand" hint="vs last year" delta="+4.2%" href="{{ route('...') }}" />
    tone: neutral | brand | success | warning | danger | info (colours the icon tile)
    delta: a signed string. "+4.2%" is shown green, "-3%" red, "0%" neutral.
--}}
@props(['label', 'value', 'icon' => null, 'tone' => 'brand', 'hint' => null, 'delta' => null, 'href' => null])
@php
    $tiles = [
        'neutral' => 'stat-icon-neutral',
        'brand' => 'stat-icon-brand',
        'success' => 'stat-icon-success',
        'warning' => 'stat-icon-warning',
        'danger' => 'stat-icon-danger',
        'info' => 'stat-icon-info',
    ];

    $deltaState = null;
    $deltaIcon = 'arrow-trending-up';
    if ($delta !== null && $delta !== '') {
        $signed = trim((string) $delta);
        if (str_starts_with($signed, '-') || str_starts_with($signed, "\u{2212}")) {
            $deltaState = 'is-down';
            $deltaIcon = 'arrow-trending-down';
        } elseif (preg_match('/^\+?0+([.,]0+)?\s*%?$/', $signed)) {
            $deltaState = 'is-flat';
            $deltaIcon = 'minus';
        } else {
            $deltaState = 'is-up';
        }
    }

    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} {{ $attributes->class(['stat']) }} @if ($href) href="{{ $href }}" @endif>
    @if ($icon)
        <span class="stat-icon {{ $tiles[$tone] ?? $tiles['brand'] }}"><x-ui.icon :name="$icon" /></span>
    @endif
    <div class="stat-body">
        <p class="stat-label">{{ $label }}</p>
        <p class="stat-value">{{ $value }}</p>
        @if ($hint || $deltaState)
            <p class="stat-hint">
                @if ($deltaState)
                    <span class="stat-delta {{ $deltaState }}"><x-ui.icon :name="$deltaIcon" variant="m" />{{ $delta }}</span>
                @endif
                @if ($hint)
                    <span>{{ $hint }}</span>
                @endif
            </p>
        @endif
    </div>
</{{ $tag }}>
