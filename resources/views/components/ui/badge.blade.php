{{--
    <x-ui.badge tone="success" dot>Paid</x-ui.badge>
    tone: neutral | brand | success | warning | danger | info
--}}
@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'neutral' => 'badge-neutral',
        'brand' => 'badge-brand',
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'danger' => 'badge-danger',
        'info' => 'badge-info',
    ];
@endphp
<span {{ $attributes->class(['badge', $tones[$tone] ?? $tones['neutral'], 'badge-dot' => $dot]) }}>{{ $slot }}</span>
