{{--
    Chromeless layout for printable documents: receipts, report cards, certificates, ID cards.

    <x-layouts.print :title="'Receipt #1042'" paper="a5">
        ...the document...
    </x-layouts.print>

    paper   a4 (default) | a5 | a6 | card (85.6 x 54 mm). Sets the @page size and the on-screen sheet proportions.
    back    URL of the "Back" button (defaults to the previous page).
    ?autoprint=1 opens the browser print dialog as soon as the page has loaded.
    Elements with class="no-print" are hidden on paper. Colours are preserved (print-color-adjust: exact).
    Always light: dark mode is not applied to documents.
--}}
@props(['title', 'paper' => 'a4', 'back' => null])
@php
    $papers = ['a4' => 'paper-a4', 'a5' => 'paper-a5', 'a6' => 'paper-a6', 'card' => 'paper-card'];
    $paperClass = $papers[$paper] ?? $papers['a4'];
    $backUrl = $back ?? url()->previous('/');
@endphp
<x-layouts.partials.document :title="$title" body-class="print-body" :theme="false" :toasts="false">
    <div x-data x-init="if (new URLSearchParams(window.location.search).has('autoprint')) { $nextTick(() => window.print()) }">
        <div class="print-toolbar no-print">
            <div class="print-toolbar-inner">
                <a href="{{ $backUrl }}" class="btn btn-secondary btn-md"><x-ui.icon name="arrow-left" />Back</a>
                <p class="print-title">{{ $title }}</p>
                <button type="button" class="btn btn-primary btn-md" @click="window.print()"><x-ui.icon name="printer" />Print</button>
            </div>
        </div>

        <div class="print-desk">
            <main id="main" {{ $attributes->class(['paper', $paperClass, 'theme-light']) }}>
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.partials.document>
