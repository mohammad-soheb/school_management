@php
    $students = [
        ['adm' => '1042', 'name' => 'Aarav Sharma', 'class' => '5-A', 'guardian' => 'Rohit Sharma', 'status' => ['Active', 'success']],
        ['adm' => '1043', 'name' => 'Aanya Iyer', 'class' => '5-B', 'guardian' => 'Meera Iyer', 'status' => ['Active', 'success']],
        ['adm' => '1107', 'name' => 'Vihaan Patel', 'class' => '7-A', 'guardian' => 'Nikhil Patel', 'status' => ['Active', 'success']],
        ['adm' => '1210', 'name' => 'Diya Menon', 'class' => '3-C', 'guardian' => 'Anil Menon', 'status' => ['On leave', 'info']],
        ['adm' => '1322', 'name' => 'Kabir Khan', 'class' => '9-A', 'guardian' => 'Sana Khan', 'status' => ['Left', 'neutral']],
    ];
    $studentPager = new \Illuminate\Pagination\LengthAwarePaginator($students, 1248, 25, 1, ['path' => url('/_ui'), 'fragment' => 'students-page']);
@endphp
<x-dev.section id="students-page" title="Sample: students" description="The standard index page: header with actions, filter bar, table in a flush card, pagination, empty state.">
    <div class="rounded-3xl border border-dashed border-line-strong p-4 sm:p-6">
        <x-ui.page-header title="Students" description="Everyone enrolled in 2026-27."
                          :breadcrumbs="[['label' => 'People', 'url' => '#students-page'], ['label' => 'Students']]">
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="arrow-up-tray">Import</x-ui.button>
                <x-ui.button icon="plus">Admit student</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.filters :reset="url('/_ui').'#students-page'" placeholder="Search name or admission no.">
            <x-form.select name="class_id" label="Class" placeholder="All classes" :options="[1 => 'Class 3', 2 => 'Class 5', 3 => 'Class 7', 4 => 'Class 9']" :selected="request('class_id')" />
            <x-form.select name="status" label="Status" placeholder="All statuses" :options="['active' => 'Active', 'leave' => 'On leave', 'left' => 'Left']" :selected="request('status')" />
        </x-ui.filters>

        <x-ui.card :padding="false" class="mt-4">
            <x-ui.table caption="Students">
                <x-slot:head>
                    <x-ui.th column="admission_no">Adm. no.</x-ui.th>
                    <x-ui.th column="name" label="Student" />
                    <x-ui.th>Class</x-ui.th>
                    <x-ui.th>Guardian</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>
                @foreach ($students as $student)
                    <tr>
                        <td class="tabular-nums text-ink-soft">{{ $student['adm'] }}</td>
                        <td>
                            <a href="#students-page" class="flex items-center gap-3 font-medium text-ink hover:text-brand-ink">
                                <x-ui.avatar :name="$student['name']" size="sm" aria-hidden="true" />{{ $student['name'] }}
                            </a>
                        </td>
                        <td>{{ $student['class'] }}</td>
                        <td class="text-ink-soft">{{ $student['guardian'] }}</td>
                        <td><x-ui.badge :tone="$student['status'][1]" dot>{{ $student['status'][0] }}</x-ui.badge></td>
                        <td class="col-actions">
                            <x-ui.button href="#students-page" variant="ghost" size="sm">View</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="border-t border-line px-4 py-3">{{ $studentPager->links() }}</div>
        </x-ui.card>

        <x-ui.card class="mt-6">
            <x-ui.empty icon="magnifying-glass" title="No students match your filters" description="Try another name or admission number, or reset the filters.">
                <x-ui.button :href="url('/_ui').'#students-page'" variant="secondary">Reset filters</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    </div>
</x-dev.section>
