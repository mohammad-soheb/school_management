<x-layouts.platform title="Schools">
    <x-ui.page-header title="Schools" description="Customer schools using the ERP.">
        <x-slot:actions>
            <x-ui.button :href="route('platform.schools.create')" icon="plus">Add school</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters :reset="route('platform.schools.index')" placeholder="Search name, code or city">
        <x-form.select name="status" label="Status" placeholder="All statuses" :selected="request('status')"
                       :options="collect(\App\Enums\Platform\SchoolStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($schools->isEmpty())
            <x-ui.empty icon="building-library" title="No schools found" description="Try a different search, or add a school." />
        @else
            @include('platform.schools._table')
        @endif
        @if ($schools->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $schools->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.platform>
