{{--
    Copy-to-clipboard button.
    <x-ui.copy text="https://school.example/pay/abc" label="Copy payment link" />
    <x-ui.copy text="INV-2026-0042" size="sm" variant="ghost">Copy number</x-ui.copy>
--}}
@props(['text', 'label' => 'Copy', 'variant' => 'secondary', 'size' => 'sm'])
<span class="contents" x-data="copy(@js((string) $text))">
    <x-ui.button :variant="$variant" :size="$size" {{ $attributes }} @click="copy()">
        <x-ui.icon name="clipboard-document" x-show="!copied" />
        <x-ui.icon name="check" x-show="copied" x-cloak />
        <span>{{ $slot->isNotEmpty() ? $slot : $label }}</span>
    </x-ui.button>
    <span class="sr-only" role="status" x-text="copied ? 'Copied to clipboard' : ''"></span>
</span>
