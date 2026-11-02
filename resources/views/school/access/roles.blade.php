<x-layouts.app title="Roles & permissions">
    <x-ui.page-header title="Roles & permissions" description="Decide what each role can see and do. Changes apply immediately."
                      :breadcrumbs="[['label' => 'Users', 'url' => route('school.access.members.index')], ['label' => 'Roles']]" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            <x-ui.card :title="$role->name" :description="$role->description">
                <x-slot:actions>
                    @if ($role->isSchoolAdmin())
                        <x-ui.badge tone="brand">Full access</x-ui.badge>
                    @elseif ($role->slug === 'parent')
                        <x-ui.badge tone="info">Parent portal</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">{{ $role->permissions_count }} permissions</x-ui.badge>
                    @endif
                </x-slot:actions>

                @if ($role->isSchoolAdmin())
                    <p class="text-sm text-ink-soft">School Admin always has every permission, so a school can never lock itself out.</p>
                @elseif ($role->slug === 'parent')
                    <p class="text-sm text-ink-soft">Parents see only their linked children in the parent portal. They hold no staff permissions.</p>
                @else
                    <x-ui.button :href="route('school.access.roles.edit', $role)" variant="secondary" size="sm" icon="adjustments-horizontal">Edit permissions</x-ui.button>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
