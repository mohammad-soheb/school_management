{{--
    GET filter bar above a list. Submitting reloads the page with ?q=...&status=... (bookmarkable, no JS needed).

    <x-ui.filters :reset="route('school.students.index')" placeholder="Search name or admission no.">
        <x-form.select name="status" label="Status" placeholder="All statuses" :options="$statuses" :selected="request('status')" />
        <x-form.select name="class_id" label="Class" placeholder="All classes" :options="$classes" :selected="request('class_id')" />
    </x-ui.filters>

    search=false removes the built-in search box (name="q"). Current ?sort= and ?dir= are carried through.
--}}
@props(['reset' => null, 'placeholder' => 'Search…', 'search' => true, 'action' => null, 'searchLabel' => 'Search'])
<form method="GET" @if ($action) action="{{ $action }}" @endif data-lock="false" role="search" {{ $attributes->class(['filters']) }}>
    <div class="filters-fields">
        @if ($search)
            <div class="filters-search">
                <label class="label" for="filters-q">{{ $searchLabel }}</label>
                <x-ui.icon name="magnifying-glass" />
                <input id="filters-q" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="{{ $placeholder }}" autocomplete="off" enterkeyhint="search">
            </div>
        @endif

        {{ $slot }}
    </div>

    @foreach (['sort', 'dir'] as $carried)
        @if (request()->filled($carried))
            <input type="hidden" name="{{ $carried }}" value="{{ request()->query($carried) }}">
        @endif
    @endforeach

    <div class="filters-actions">
        <x-ui.button type="submit" variant="primary" icon="funnel">Filter</x-ui.button>
        @if ($reset)
            <x-ui.button :href="$reset" variant="ghost">Reset</x-ui.button>
        @endif
    </div>
</form>
