@php $canManage = auth()->user()->can('setup.classes.manage'); @endphp
<x-layouts.app title="Classes & sections">
    <x-ui.page-header title="Classes & sections" description="Listed in promotion order. Open a class to manage its sections, class teachers and subjects."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Classes']]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button :href="route('school.setup.classes.create')" icon="plus">New class</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @include('school.setup._nav')

    <x-ui.card :padding="false">
        @if ($classes->isEmpty())
            <x-ui.empty icon="rectangle-group" title="No classes yet" description="Add your classes (for example Nursery, KG, Class 1 ... Class 10), then their sections.">
                @if ($canManage)
                    <x-ui.button :href="route('school.setup.classes.create')" icon="plus">New class</x-ui.button>
                @endif
            </x-ui.empty>
        @else
            <x-ui.table caption="Classes">
                <x-slot:head>
                    <x-ui.th class="w-16">Order</x-ui.th>
                    <x-ui.th>Class</x-ui.th>
                    <x-ui.th class="num">Sections</x-ui.th>
                    <x-ui.th class="num">Subjects</x-ui.th>
                    <x-ui.th>Promotes to</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>

                @foreach ($classes as $class)
                    <tr @class(['opacity-60' => ! $class->is_active])>
                        <td class="tabular-nums text-ink-faint">{{ $class->sort_order }}</td>
                        <td>
                            <a href="{{ route('school.setup.classes.show', $class) }}" class="font-medium text-ink hover:text-brand-ink">{{ $class->name }}</a>
                            @if ($class->code)<span class="ml-1 text-sm text-ink-faint">({{ $class->code }})</span>@endif
                        </td>
                        <td class="num tabular-nums">{{ $class->sections_count }}</td>
                        <td class="num tabular-nums">{{ $class->subjects_count }}</td>
                        <td class="text-ink-soft">{{ $class->nextClass?->name ?? 'Final class (graduates)' }}</td>
                        <td>
                            @if ($class->is_active)
                                <x-ui.badge tone="success" dot>Active</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" dot>Inactive</x-ui.badge>
                            @endif
                        </td>
                        <td class="col-actions">
                            <div class="flex justify-end gap-1">
                                <x-ui.button :href="route('school.setup.classes.show', $class)" variant="ghost" size="sm" icon="eye">Open</x-ui.button>
                                @if ($canManage)
                                    <x-ui.button :href="route('school.setup.classes.edit', $class)" variant="ghost" size="sm" icon="pencil-square">Edit</x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-layouts.app>
