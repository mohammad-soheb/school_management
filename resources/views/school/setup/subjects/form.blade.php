@php
    $editing = $subject !== null;
    $title = $editing ? 'Edit '.$subject->name : 'New subject';
    $types = collect(\App\Enums\SchoolSetup\SubjectType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title"
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Subjects', 'url' => route('school.setup.subjects.index')], ['label' => $editing ? 'Edit' : 'New']]" />

    <x-ui.card class="max-w-2xl">
        <x-form.form :action="$editing ? route('school.setup.subjects.update', $subject) : route('school.setup.subjects.store')" :method="$editing ? 'PUT' : 'POST'" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="name" label="Name" :value="$subject?->name" required maxlength="100" placeholder="Mathematics" class="sm:col-span-2" />
                <x-form.input name="code" label="Code" :value="$subject?->code" maxlength="20" placeholder="MATH" input-class="uppercase" hint="Unique in the school." />
            </div>
            <fieldset>
                <legend class="label">Type</legend>
                <div class="mt-2 grid gap-1 sm:grid-cols-2">
                    @foreach ($types as $value => $label)
                        <x-form.radio name="type" :value="$value" :label="$label"
                                      :hint="$value === 'scholastic' ? 'Marks and grades (English, Maths ...)' : 'Graded activities (Art, Sports, Conduct ...)'"
                                      :checked="($subject?->type?->value ?? 'scholastic') === $value" />
                    @endforeach
                </div>
                <x-form.error name="type" />
            </fieldset>
            <x-form.input name="sort_order" label="Order" type="number" min="0" max="999" :value="$subject?->sort_order ?? 0" hint="Default position on report cards." class="max-w-xs" />

            <input type="hidden" name="is_active" value="0">
            <x-form.toggle name="is_active" label="Active" :checked="$subject?->is_active ?? true" hint="Inactive subjects keep their history but are hidden for new records." />

            <x-form.actions>
                <x-ui.button :href="route('school.setup.subjects.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Create subject' }}</x-ui.button>
            </x-form.actions>
        </x-form.form>
    </x-ui.card>
</x-layouts.app>
