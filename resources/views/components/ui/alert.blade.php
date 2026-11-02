{{--
    <x-ui.alert tone="warning" title="Fees are overdue" dismissible>Three instalments are past their due date.</x-ui.alert>
    tone: info | success | warning | danger | brand | neutral
--}}
@props(['tone' => 'info', 'title' => null, 'dismissible' => false])
@php
    $classes = [
        'neutral' => 'alert-neutral',
        'brand' => 'alert-brand',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger',
        'info' => 'alert-info',
    ];
    $icons = [
        'neutral' => 'information-circle',
        'brand' => 'sparkles',
        'success' => 'check-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'x-circle',
        'info' => 'information-circle',
    ];
    $tone = array_key_exists($tone, $classes) ? $tone : 'info';
    $role = in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status';
@endphp
<div {{ $attributes->class(['alert', $classes[$tone]]) }} role="{{ $role }}" @if ($dismissible) x-data="{ shown: true }" x-show="shown" x-transition.opacity @endif>
    <x-ui.icon :name="$icons[$tone]" variant="s" class="alert-icon" />
    <div class="alert-body">
        @if ($title)
            <p class="alert-title">{{ $title }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div>{{ $slot }}</div>
        @endif
    </div>
    @if ($dismissible)
        <button type="button" class="alert-close" @click="shown = false" aria-label="Dismiss">
            <x-ui.icon name="x-mark" />
        </button>
    @endif
</div>
