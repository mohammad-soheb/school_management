<x-layouts.app title="Set homework">
    <x-ui.page-header title="Set homework"
        :description="$year ? 'Academic year '.$year->name : null"
        :breadcrumbs="[['label' => 'Homework', 'url' => route('school.homework.index')], ['label' => 'Set homework']]" />

    @if ($year === null)
        <x-ui.card>
            <x-ui.empty icon="calendar-days" title="No current academic year"
                        description="Homework belongs to an academic year. Ask your School Admin to set the current year in School setup." />
        </x-ui.card>
    @elseif ($options === [])
        <x-ui.card>
            <x-ui.empty icon="book-open" title="No classes to set homework for"
                        description="You are not assigned to teach a subject in any class this year. Ask your School Admin to add your teaching assignments." />
        </x-ui.card>
    @else
        <x-form.form :action="route('school.homework.store')" :files="true" class="space-y-6">
            @include('school.homework._form')

            <x-form.actions sticky>
                <x-ui.button :href="route('school.homework.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">Set homework</x-ui.button>
            </x-form.actions>
        </x-form.form>
    @endif
</x-layouts.app>
