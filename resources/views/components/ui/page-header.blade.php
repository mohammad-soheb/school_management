{{--
    <x-ui.page-header title="Students" description="Everyone enrolled this year" :breadcrumbs="[['label' => 'Home', 'url' => '/'], ['label' => 'Students']]">
        <x-slot:actions><x-ui.button href="..." icon="plus">Admit student</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    breadcrumbs: list of ['label' => string, 'url' => ?string]. The last item (or any item without a url) is the current page.
    The default slot renders under the description (status badges, meta line ...).
--}}
@props(['title', 'description' => null, 'breadcrumbs' => []])
@php
    $crumbs = array_values($breadcrumbs ?? []);
    $hasActions = isset($actions) && $actions->isNotEmpty();
@endphp
<header {{ $attributes->class(['page-header']) }}>
    @if (count($crumbs) > 0)
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <ol>
                @foreach ($crumbs as $crumb)
                    @php($isLast = $loop->last || empty($crumb['url']))
                    <li>
                        @if ($isLast)
                            <span aria-current="page">{{ $crumb['label'] }}</span>
                        @else
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                            <x-ui.icon name="chevron-right" variant="m" />
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="page-header-row">
        <div class="min-w-0">
            <h1 class="page-title">{{ $title }}</h1>
            @if ($description)
                <p class="page-description">{{ $description }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <div class="mt-3">{{ $slot }}</div>
            @endif
        </div>
        @if ($hasActions)
            <div class="page-actions">{{ $actions }}</div>
        @endif
    </div>
</header>
