@php
    $editing = $holiday !== null;
    $title = $editing ? 'Edit '.$holiday->title : 'Add to calendar';
    $types = \App\Enums\SchoolSetup\HolidayType::cases();
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" description="The entry is filed under the academic year that contains its dates."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Calendar', 'url' => route('school.setup.holidays.index')], ['label' => $editing ? 'Edit' : 'New']]" />

    <x-ui.card class="max-w-2xl">
        <x-form.form :action="$editing ? route('school.setup.holidays.update', $holiday) : route('school.setup.holidays.store')" :method="$editing ? 'PUT' : 'POST'" class="space-y-5">
            <x-form.input name="title" label="Title" :value="$holiday?->title" required maxlength="120" placeholder="Diwali vacation" />

            <fieldset>
                <legend class="label">Type</legend>
                <div class="mt-2 grid gap-1 sm:grid-cols-3">
                    @foreach ($types as $type)
                        <x-form.radio name="type" :value="$type->value" :label="$type->label()"
                                      :hint="match ($type->value) { 'holiday' => 'School closed', 'event' => 'School open', default => 'School open' }"
                                      :checked="($holiday?->type?->value ?? 'holiday') === $type->value" />
                    @endforeach
                </div>
                <x-form.error name="type" />
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.date name="starts_on" label="From" :value="$holiday?->starts_on?->format('Y-m-d') ?? $date" required />
                <x-form.date name="ends_on" label="To" :value="$holiday?->ends_on?->format('Y-m-d')" hint="Leave empty for a single day." />
            </div>

            <x-form.actions>
                <x-ui.button :href="route('school.setup.holidays.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Add to calendar' }}</x-ui.button>
            </x-form.actions>
        </x-form.form>
    </x-ui.card>
</x-layouts.app>
