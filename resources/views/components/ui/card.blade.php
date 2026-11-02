{{--
    <x-ui.card title="Student details" description="Personal and admission data" :padding="true">
        <x-slot:actions><x-ui.button size="sm" variant="secondary">Edit</x-ui.button></x-slot:actions>
        ...content...
        <x-slot:footer>...</x-slot:footer>
    </x-ui.card>

    padding=false is for content that must touch the edges (a table, a list).
--}}
@props(['title' => null, 'description' => null, 'padding' => true])
@php
    $hasActions = isset($actions) && $actions->isNotEmpty();
    $hasFooter = isset($footer) && $footer->isNotEmpty();
@endphp
<section {{ $attributes->class(['card', 'card-flush' => ! $padding]) }}>
    @if ($title || $description || $hasActions)
        <header class="card-header">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="card-title">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="card-description">{{ $description }}</p>
                @endif
            </div>
            @if ($hasActions)
                <div class="card-actions">{{ $actions }}</div>
            @endif
        </header>
    @endif

    @if ($slot->isNotEmpty())
        <div @class(['card-body' => $padding])>{{ $slot }}</div>
    @endif

    @if ($hasFooter)
        <footer class="card-footer">{{ $footer }}</footer>
    @endif
</section>
