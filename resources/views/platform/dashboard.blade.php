<x-layouts.platform title="Platform overview">
    <x-ui.page-header title="Platform overview" description="Every customer school at a glance.">
        <x-slot:actions>
            <x-ui.button :href="route('platform.schools.create')" icon="plus">Add school</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Schools" :value="number_format($stats['schools'])" icon="building-library" tone="brand" :href="route('platform.schools.index')" />
        <x-ui.stat label="Active" :value="number_format($stats['active'])" icon="check-badge" tone="success" />
        <x-ui.stat label="Suspended" :value="number_format($stats['suspended'])" icon="pause-circle" tone="warning" />
        <x-ui.stat label="School users" :value="number_format($stats['users'])" icon="users" tone="info" />
    </div>

    {{-- Drop-in panels: a module adds resources/views/platform/dashboard-panels/NN-name.blade.php. --}}
    @foreach (glob(resource_path('views/platform/dashboard-panels/*.blade.php')) ?: [] as $panel)
        <div class="mt-6">@include('platform.dashboard-panels.'.basename($panel, '.blade.php'))</div>
    @endforeach

    <x-ui.card title="Schools" :padding="false" class="mt-6">
        <x-slot:actions>
            <x-ui.button :href="route('platform.schools.index')" variant="ghost" size="sm" icon-right="arrow-right">View all</x-ui.button>
        </x-slot:actions>
        @if ($recentSchools->isEmpty())
            <x-ui.empty icon="building-library" title="No schools yet" description="Add your first customer school to get started.">
                <x-ui.button :href="route('platform.schools.create')" icon="plus">Add school</x-ui.button>
            </x-ui.empty>
        @else
            @include('platform.schools._table', ['schools' => $recentSchools])
        @endif
    </x-ui.card>
</x-layouts.platform>
