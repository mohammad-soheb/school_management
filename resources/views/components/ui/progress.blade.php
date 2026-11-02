{{--
    <x-ui.progress :value="72" :max="100" tone="success" label="Fees collected" show-value />
    tone: neutral | brand | success | warning | danger | info.  Uses a native <progress>, so no inline style is needed.
--}}
@props(['value' => 0, 'max' => 100, 'tone' => 'brand', 'label' => null, 'showValue' => false, 'large' => false])
@php
    $tones = [
        'neutral' => 'progress-neutral',
        'brand' => 'progress-brand',
        'success' => 'progress-success',
        'warning' => 'progress-warning',
        'danger' => 'progress-danger',
        'info' => 'progress-info',
    ];
    $max = (float) $max > 0 ? (float) $max : 100.0;
    $current = max(0.0, min($max, (float) $value));
    $percent = (int) round($current / $max * 100);
@endphp
<div class="progress-row">
    <progress {{ $attributes->class(['progress', $tones[$tone] ?? $tones['brand'], 'progress-lg' => $large]) }}
              value="{{ $current }}" max="{{ $max }}" @if ($label) aria-label="{{ $label }}" @endif>{{ $percent }}%</progress>
    @if ($showValue)
        <span class="progress-value">{{ $percent }}%</span>
    @endif
</div>
