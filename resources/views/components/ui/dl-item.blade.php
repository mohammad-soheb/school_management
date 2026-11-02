{{-- One label/value pair of <x-ui.dl>. An empty value renders an em dash. --}}
@props(['label'])
<div {{ $attributes->class(['dl-item']) }}>
    <dt class="dl-term">{{ $label }}</dt>
    <dd class="dl-desc">
        @if ($slot->isEmpty())
            <span class="text-ink-faint" aria-label="Not provided">&mdash;</span>
        @else
            {{ $slot }}
        @endif
    </dd>
</div>
