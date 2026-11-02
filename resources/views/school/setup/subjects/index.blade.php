@php $canManage = auth()->user()->can('setup.subjects.manage'); @endphp
<x-layouts.app title="Subjects">
    <x-ui.page-header title="Subjects" description="Every subject taught in the school. Map them to classes on each class page."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Subjects']]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button :href="route('school.setup.subjects.create')" icon="plus">New subject</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @include('school.setup._nav')

    @foreach (['subject', 'record'] as $errorKey)
        @error($errorKey)<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @endforeach

    <x-ui.filters :reset="route('school.setup.subjects.index')" placeholder="Search name or code">
        <x-form.select name="type" label="Type" placeholder="All types" :options="$types" :selected="request('type')" />
        <x-form.select name="status" label="Status" placeholder="Active and inactive" :options="['active' => 'Active', 'inactive' => 'Inactive']" :selected="request('status')" />
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($subjects->isEmpty())
            @if (request()->hasAny(['q', 'type', 'status']))
                <x-ui.empty icon="magnifying-glass" title="No matching subjects" description="Try a different search or filter." />
            @else
                <x-ui.empty icon="book-open" title="No subjects yet" description="Add the subjects your school teaches, such as English, Hindi, Mathematics.">
                    @if ($canManage)
                        <x-ui.button :href="route('school.setup.subjects.create')" icon="plus">New subject</x-ui.button>
                    @endif
                </x-ui.empty>
            @endif
        @else
            <x-ui.table caption="Subjects">
                <x-slot:head>
                    <x-ui.th>Subject</x-ui.th>
                    <x-ui.th>Code</x-ui.th>
                    <x-ui.th>Type</x-ui.th>
                    <x-ui.th class="num">Classes</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    @if ($canManage)<th class="col-actions"><span class="sr-only">Actions</span></th>@endif
                </x-slot:head>
                @foreach ($subjects as $subject)
                    <tr @class(['opacity-60' => ! $subject->is_active])>
                        <td class="font-medium text-ink">{{ $subject->name }}</td>
                        <td class="font-mono text-sm text-ink-soft">{{ $subject->code ?? '—' }}</td>
                        <td><x-ui.badge :tone="$subject->type->value === 'scholastic' ? 'brand' : 'info'">{{ $subject->type->label() }}</x-ui.badge></td>
                        <td class="num tabular-nums">{{ $subject->class_subjects_count }}</td>
                        <td>
                            @if ($subject->is_active)
                                <x-ui.badge tone="success" dot>Active</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" dot>Inactive</x-ui.badge>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="col-actions">
                                <div class="flex justify-end gap-1">
                                    <x-ui.button :href="route('school.setup.subjects.edit', $subject)" variant="ghost" size="sm" icon="pencil-square">Edit</x-ui.button>
                                    @if ($subject->is_active)
                                        <x-ui.confirm :action="route('school.setup.subjects.deactivate', $subject)" method="POST" :title="'Deactivate '.$subject->name.'?'"
                                                      message="It keeps its history but is hidden for new class mappings, exams and assignments." confirm="Deactivate" tone="warning">
                                            <x-ui.button variant="ghost" size="sm" icon="archive-box">Deactivate</x-ui.button>
                                        </x-ui.confirm>
                                    @else
                                        <x-form.form :action="route('school.setup.subjects.activate', $subject)" class="inline">
                                            <x-ui.button type="submit" variant="ghost" size="sm" icon="arrow-uturn-left">Activate</x-ui.button>
                                        </x-form.form>
                                    @endif
                                    @if ($subject->class_subjects_count === 0)
                                        <x-ui.confirm :action="route('school.setup.subjects.destroy', $subject)" method="DELETE" :title="'Delete '.$subject->name.'?'"
                                                      message="Only a subject that was never used can be deleted." confirm="Delete">
                                            <x-ui.button variant="ghost" size="sm" icon="trash" class="btn-icon" :aria-label="'Delete '.$subject->name" />
                                        </x-ui.confirm>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($subjects->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $subjects->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
