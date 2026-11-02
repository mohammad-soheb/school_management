{{-- Section wrapper used only by the dev UI gallery (/_ui). --}}
@props(['id', 'title', 'description' => null])
<section id="{{ $id }}" class="scroll-mt-24" aria-labelledby="{{ $id }}-title">
    <div class="mb-5 flex items-end justify-between gap-4 border-b border-line pb-3">
        <div>
            <h2 id="{{ $id }}-title" class="text-xl font-semibold tracking-tight text-ink">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-sm text-ink-soft">{{ $description }}</p>
            @endif
        </div>
        <a href="#top" class="hidden text-xs font-medium text-ink-faint hover:text-ink sm:inline">Back to top</a>
    </div>
    {{ $slot }}
</section>
