{{-- Light / dark switch. The icon swap is pure CSS (.dark on <html>); the click handler lives in core/theme.js. --}}
<button type="button" x-data {{ $attributes->class(['icon-btn']) }} @click="$store.theme.toggle()" :aria-pressed="$store.theme.dark ? 'true' : 'false'"
        aria-label="Dark mode" title="Toggle dark mode">
    <x-ui.icon name="moon" class="dark:hidden" />
    <x-ui.icon name="sun" class="hidden dark:block" />
</button>
