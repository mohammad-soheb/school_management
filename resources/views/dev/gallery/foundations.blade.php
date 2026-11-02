<x-dev.section id="foundations" title="Foundations" description="Colour tokens flip between light and dark; type is Inter Variable.">
    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Surfaces" class="lg:col-span-1">
            <ul class="space-y-3 text-sm">
                @foreach ([
                    ['bg-canvas', 'canvas', 'App background'],
                    ['bg-surface', 'surface', 'Cards, tables'],
                    ['bg-surface-muted', 'surface-muted', 'Table headers, hover rows'],
                    ['bg-surface-raised', 'surface-raised', 'Popovers, modals'],
                    ['bg-field', 'field', 'Inputs'],
                ] as [$swatch, $name, $note])
                    <li class="flex items-center gap-3">
                        <span class="h-9 w-14 shrink-0 rounded-lg border border-line-strong {{ $swatch }}"></span>
                        <span><code class="text-ink">{{ $name }}</code> <span class="text-ink-faint">{{ $note }}</span></span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.card title="Text & lines">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-ink"></span><span><code>ink</code> <span class="text-ink-faint">Primary text</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-ink-soft"></span><span><code>ink-soft</code> <span class="text-ink-faint">Secondary text</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-ink-faint"></span><span><code>ink-faint</code> <span class="text-ink-faint">Hints, placeholders</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg border border-line bg-line"></span><span><code>line</code> <span class="text-ink-faint">Hairlines</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-line-strong"></span><span><code>line-strong</code> <span class="text-ink-faint">Input borders</span></span></li>
            </ul>
        </x-ui.card>

        <x-ui.card title="Brand & status">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-brand-600"></span><span><code>brand-600</code> <span class="text-ink-faint">Primary action</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-success"></span><span><code>success</code> <span class="font-medium text-success-ink">success-ink</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-warning"></span><span><code>warning</code> <span class="font-medium text-warning-ink">warning-ink</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-danger"></span><span><code>danger</code> <span class="font-medium text-danger-ink">danger-ink</span></span></li>
                <li class="flex items-center gap-3"><span class="h-9 w-14 shrink-0 rounded-lg bg-info"></span><span><code>info</code> <span class="font-medium text-info-ink">info-ink</span></span></li>
            </ul>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6" title="Typography">
        <div class="space-y-3">
            <p class="text-4xl font-semibold tracking-tight text-ink">Heading 4xl &middot; Aa 0123456789</p>
            <p class="text-2xl font-semibold tracking-tight text-ink">Page title 2xl semibold</p>
            <p class="text-lg font-semibold text-ink">Section title lg semibold</p>
            <p class="text-base text-ink">Body base: attendance, fees and report cards in one calm place.</p>
            <p class="text-sm text-ink-soft">Body small (default UI size, 14px): secondary description text on a surface.</p>
            <p class="text-xs text-ink-faint">Caption xs: updated 29 Oct 2026, 9:15 am &middot; <code>monospace 0.8125rem</code></p>
            <p class="text-sm tabular-nums text-ink">Tabular figures: ₹ 1,24,500.00 &middot; 9,87,654 &middot; 1111.11 &middot; 8888.88</p>
            <p class="text-sm text-ink">Hindi / Devanagari falls back to the system font: विद्यालय शुल्क रसीद, उपस्थिति पंजीका</p>
        </div>
    </x-ui.card>
</x-dev.section>
