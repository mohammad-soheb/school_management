<x-layouts.app :title="'Edit: '.$homework->title">
    <x-ui.page-header :title="'Edit homework'" :description="$homework->title"
        :breadcrumbs="[
            ['label' => 'Homework', 'url' => route('school.homework.index')],
            ['label' => $homework->title, 'url' => route('school.homework.show', $homework)],
            ['label' => 'Edit'],
        ]" />

    @if ($homework->isArchived())
        <x-ui.alert tone="warning" title="This homework is archived" class="mb-6">Restore it before making changes.</x-ui.alert>
    @endif

    <x-form.form :action="route('school.homework.update', $homework)" method="PUT" :files="true" class="space-y-6">
        @include('school.homework._form', ['homework' => $homework])

        <x-form.actions sticky>
            <x-ui.button :href="route('school.homework.show', $homework)" variant="ghost">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.app>
