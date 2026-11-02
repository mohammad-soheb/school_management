<x-layouts.app :title="'Permissions · '.$role->name">
    <x-ui.page-header :title="$role->name" description="Tick what this role may do. Teachers' access to students, attendance and marks is also limited to their assigned classes."
                      :breadcrumbs="[['label' => 'Users', 'url' => route('school.access.members.index')], ['label' => 'Roles', 'url' => route('school.access.roles.index')], ['label' => $role->name]]" />

    @if ($role->isSchoolAdmin() || $role->slug === 'parent')
        <x-ui.alert tone="info">This role is fixed and cannot be edited.</x-ui.alert>
    @else
        <x-form.form :action="route('school.access.roles.update', $role)" method="PUT">
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($groups as $key => $group)
                    <x-ui.card :title="$group['label']" x-data="{ all: false }">
                        <x-slot:actions>
                            <button type="button" class="rounded text-sm font-medium text-brand-ink underline-offset-4 hover:underline"
                                    @click="all = !all; $el.closest('section').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = all)"
                                    x-text="all ? 'Clear all' : 'Select all'"></button>
                        </x-slot:actions>

                        <div class="space-y-1">
                            @foreach ($group['permissions'] as $permission => $label)
                                <x-form.checkbox name="permissions[]" :value="$permission" :label="$label" :checked="in_array($permission, $granted, true)" />
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <x-form.actions sticky class="mt-6">
                <x-ui.button :href="route('school.access.roles.index')" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Save permissions</x-ui.button>
            </x-form.actions>
        </x-form.form>
    @endif
</x-layouts.app>
