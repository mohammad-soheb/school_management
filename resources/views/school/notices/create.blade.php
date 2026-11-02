<x-layouts.app title="New notice">
    <x-ui.page-header title="New notice" description="Save it as a draft, or publish it now (or at a scheduled time)."
        :breadcrumbs="[['label' => 'Notices', 'url' => route('school.notices.manage')], ['label' => 'New notice']]" />

    <x-form.form :action="route('school.notices.store')" :files="true" class="space-y-6">
        @include('school.notices._form', ['notice' => null])

        <x-form.actions sticky>
            <x-ui.button :href="route('school.notices.manage')" variant="ghost">Cancel</x-ui.button>
            <x-ui.button type="submit" name="action" value="draft" variant="secondary" icon="document">Save draft</x-ui.button>
            <x-ui.button type="submit" name="action" value="publish" icon="megaphone">Publish</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.app>
