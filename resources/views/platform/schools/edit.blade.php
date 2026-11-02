<x-layouts.platform :title="'Edit '.$school->name">
    <x-ui.page-header :title="'Edit '.$school->name"
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => $school->name, 'url' => route('platform.schools.show', $school)], ['label' => 'Edit']]" />

    <x-form.form :action="route('platform.schools.update', $school)" method="PUT" class="space-y-6">
        <x-ui.card title="School details">
            @include('platform.schools._form', ['school' => $school])
        </x-ui.card>
        <x-form.actions sticky>
            <x-ui.button :href="route('platform.schools.show', $school)" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save changes</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.platform>
