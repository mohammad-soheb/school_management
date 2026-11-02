<x-layouts.platform title="Add school">
    <x-ui.page-header title="Add a school" description="Creates the school with its default roles and invites the first School Admin."
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => 'Add school']]" />

    <x-form.form :action="route('platform.schools.store')" class="space-y-6">
        <x-ui.card title="School details">
            @include('platform.schools._form')
        </x-ui.card>

        <x-ui.card title="First School Admin" description="They receive an invitation email to create their login.">
            <x-form.input name="admin_email" label="Admin email" type="email" required />
        </x-ui.card>

        <x-form.actions sticky>
            <x-ui.button :href="route('platform.schools.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Create school</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.platform>
