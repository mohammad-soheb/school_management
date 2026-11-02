@php
    $editing = $year !== null;
    $title = $editing ? 'Edit '.$year->name : 'New academic year';
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" description="Years cannot overlap. Most Indian schools run 1 April to 31 March."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Academic years', 'url' => route('school.setup.years.index')], ['label' => $editing ? 'Edit' : 'New']]" />

    <x-ui.card class="max-w-2xl">
        <x-form.form :action="$editing ? route('school.setup.years.update', $year) : route('school.setup.years.store')" :method="$editing ? 'PUT' : 'POST'" class="space-y-5">
            <x-form.input name="name" label="Name" :value="$year?->name ?? $suggested['name'] ?? null" required maxlength="20" placeholder="2026-27" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.date name="starts_on" label="Starts on" :value="$year?->starts_on?->format('Y-m-d') ?? $suggested['starts_on'] ?? null" required />
                <x-form.date name="ends_on" label="Ends on" :value="$year?->ends_on?->format('Y-m-d') ?? $suggested['ends_on'] ?? null" required />
            </div>
            @unless ($editing)
                <x-form.checkbox name="make_current" label="Make this the current year now" hint="The first year you create always becomes current." />
            @endunless

            <x-form.actions>
                <x-ui.button :href="route('school.setup.years.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Create year' }}</x-ui.button>
            </x-form.actions>
        </x-form.form>
    </x-ui.card>
</x-layouts.app>
