{{--
    Loading placeholders. Pair with aria-busy on the container you are filling.
    <x-ui.skeleton lines="3" />                 three text lines (last one shorter)
    <x-ui.skeleton variant="avatar" />          round
    <x-ui.skeleton variant="block" />           card-sized block
--}}
@props(['variant' => 'text', 'lines' => 1])
@if ($variant === 'text')
    <div {{ $attributes->class(['space-y-2.5']) }} role="status" aria-label="Loading">
        @for ($i = 1; $i <= max(1, (int) $lines); $i++)
            <span @class(['skeleton', 'skeleton-text', 'is-short' => $i === (int) $lines && $lines > 1])></span>
        @endfor
        <span class="sr-only">Loading…</span>
    </div>
@else
    <span {{ $attributes->class(['skeleton', $variant === 'avatar' ? 'skeleton-avatar' : 'skeleton-block']) }} role="status" aria-label="Loading"></span>
@endif
