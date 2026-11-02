@php
    $editing = $class !== null;
    $title = $editing ? 'Edit '.$class->name : 'New class';
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" description="A class is a grade or standard. Sections (A, B ...) are added on the class page."
                      :breadcrumbs="array_values(array_filter([
                          ['label' => 'School setup', 'url' => route('school.setup.index')],
                          ['label' => 'Classes', 'url' => route('school.setup.classes.index')],
                          $editing ? ['label' => $class->name, 'url' => route('school.setup.classes.show', $class)] : null,
                          ['label' => $editing ? 'Edit' : 'New'],
                      ]))" />

    <x-ui.card class="max-w-2xl">
        <x-form.form :action="$editing ? route('school.setup.classes.update', $class) : route('school.setup.classes.store')" :method="$editing ? 'PUT' : 'POST'" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="name" label="Name" :value="$class?->name" required maxlength="60" placeholder="Class 5" class="sm:col-span-2" />
                <x-form.input name="code" label="Short code" :value="$class?->code" maxlength="20" placeholder="5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="sort_order" label="Order" type="number" min="0" max="999" :value="$class?->sort_order ?? 0" hint="Lower comes first." />
                <x-form.select name="next_class_id" label="Promotes to" :options="$classOptions" :selected="$class?->next_class_id"
                               placeholder="None (final class: students graduate)" class="sm:col-span-2"
                               hint="Used by year-end promotion. Cannot lead back to this class." />
            </div>

            @if ($editing)
                <input type="hidden" name="is_active" value="0">
                <x-form.toggle name="is_active" label="Active" :checked="(bool) $class->is_active"
                               hint="Inactive classes keep their history but are hidden when admitting or assigning." />
            @endif

            <x-form.actions>
                <x-ui.button :href="$editing ? route('school.setup.classes.show', $class) : route('school.setup.classes.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Create class' }}</x-ui.button>
            </x-form.actions>
        </x-form.form>
    </x-ui.card>

    @if ($editing)
        <x-ui.card title="Delete this class" description="Only a class that was never used can be deleted. A class with students or teacher assignments can only be deactivated." class="mt-6 max-w-2xl">
            @error('class')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
            @error('record')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
            <x-ui.confirm :action="route('school.setup.classes.destroy', $class)" method="DELETE"
                          :title="'Delete '.$class->name.'?'" message="Its sections and subject list are removed too. This cannot be undone."
                          confirm="Delete class">
                <x-ui.button variant="danger" icon="trash">Delete class</x-ui.button>
            </x-ui.confirm>
        </x-ui.card>
    @endif
</x-layouts.app>
