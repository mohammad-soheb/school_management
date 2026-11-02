@php
    $canManage = auth()->user()->can('setup.classes.manage');
    $mapped = $class->subjects->keyBy('id');
    $editingSection = (int) old('section_id');
    $subjectsOld = old('subjects');
    $subjectsOld = is_array($subjectsOld) ? $subjectsOld : (old('subjects_form') ? [] : null);
@endphp
<x-layouts.app :title="$class->name">
    <x-ui.page-header :title="$class->name" :description="'Promotes to: '.($class->nextClass?->name ?? 'none (final class, students graduate)')"
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Classes', 'url' => route('school.setup.classes.index')], ['label' => $class->name]]">
        <div class="mt-2 flex flex-wrap gap-2">
            @if ($class->is_active)
                <x-ui.badge tone="success" dot>Active</x-ui.badge>
            @else
                <x-ui.badge tone="neutral" dot>Inactive</x-ui.badge>
            @endif
            @if ($class->code)<x-ui.badge>Code {{ $class->code }}</x-ui.badge>@endif
        </div>
        @if ($canManage)
            <x-slot:actions>
                @if ($class->is_active)
                    <x-ui.confirm :action="route('school.setup.classes.deactivate', $class)" method="POST" :title="'Deactivate '.$class->name.'?'"
                                  message="It keeps all its history but is hidden when admitting students or assigning teachers." confirm="Deactivate" tone="warning">
                        <x-ui.button variant="secondary" icon="archive-box">Deactivate</x-ui.button>
                    </x-ui.confirm>
                @else
                    <x-form.form :action="route('school.setup.classes.activate', $class)" class="inline">
                        <x-ui.button type="submit" variant="secondary" icon="arrow-uturn-left">Activate</x-ui.button>
                    </x-form.form>
                @endif
                <x-ui.button :href="route('school.setup.classes.edit', $class)" variant="secondary" icon="pencil-square">Edit class</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @foreach (['section', 'subjects', 'record'] as $errorKey)
        @error($errorKey)<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    @endforeach

    <div class="grid gap-6 xl:grid-cols-5">
        {{-- Sections --}}
        <x-ui.card title="Sections" :description="$class->sections->count().' '.\Illuminate\Support\Str::plural('section', $class->sections->count()).' · student counts are for the current year'" :padding="false" class="xl:col-span-3">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button size="sm" icon="plus" @click="$dispatch('open-modal', 'section-new')">Add section</x-ui.button>
                </x-slot:actions>
            @endif

            @if ($class->sections->isEmpty())
                <x-ui.empty icon="squares-2x2" title="No sections yet" description="Even a class with one division needs a section (for example A)." />
            @else
                <x-ui.table caption="Sections of {{ $class->name }}">
                    <x-slot:head>
                        <x-ui.th>Section</x-ui.th>
                        <x-ui.th>Class teacher</x-ui.th>
                        <x-ui.th class="num">Students</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        @if ($canManage)<th class="col-actions"><span class="sr-only">Actions</span></th>@endif
                    </x-slot:head>
                    @foreach ($class->sections as $section)
                        @php
                            $students = $studentCounts[$section->id] ?? 0;
                        @endphp
                        <tr @class(['opacity-60' => ! $section->is_active])>
                            <td class="font-medium text-ink">{{ $section->name }}</td>
                            <td>
                                @if ($section->classTeacher)
                                    <span class="text-ink">{{ $section->classTeacher->fullName() }}</span>
                                @else
                                    <span class="text-sm text-warning-ink">Not chosen</span>
                                @endif
                            </td>
                            <td class="num tabular-nums">
                                {{ $students }}@if ($section->capacity)<span class="text-ink-faint"> / {{ $section->capacity }}</span>@endif
                            </td>
                            <td>
                                @if ($section->is_active)
                                    <x-ui.badge tone="success" dot>Active</x-ui.badge>
                                @else
                                    <x-ui.badge tone="neutral" dot>Inactive</x-ui.badge>
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="col-actions">
                                    <x-ui.dropdown align="right" width="48">
                                        <x-slot:trigger>
                                            <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Actions for section {{ $section->name }}">
                                                <x-ui.icon name="ellipsis-vertical" />
                                            </button>
                                        </x-slot:trigger>
                                        <x-ui.dropdown-link as="button" type="button" icon="pencil-square" @click="$dispatch('open-modal', 'section-{{ $section->id }}')">Edit</x-ui.dropdown-link>
                                        <form method="POST" action="{{ route($section->is_active ? 'school.setup.sections.deactivate' : 'school.setup.sections.activate', $section) }}">
                                            @csrf
                                            <x-ui.dropdown-link as="button" :icon="$section->is_active ? 'archive-box' : 'arrow-uturn-left'">{{ $section->is_active ? 'Deactivate' : 'Activate' }}</x-ui.dropdown-link>
                                        </form>
                                        <hr class="dropdown-divider">
                                        <x-ui.confirm :action="route('school.setup.sections.destroy', $section)" method="DELETE"
                                                      :title="'Delete section '.$section->name.'?'"
                                                      message="Only a section that was never used can be deleted. A used section can be deactivated instead."
                                                      confirm="Delete section">
                                            <x-ui.dropdown-link as="button" type="button" danger icon="trash">Delete</x-ui.dropdown-link>
                                        </x-ui.confirm>
                                    </x-ui.dropdown>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        {{-- Subjects --}}
        <x-ui.card title="Subjects" :description="$class->subjects->count().' taught in this class'" class="xl:col-span-2">
            @if ($canManage)
                @if ($subjects->isEmpty())
                    <x-ui.empty icon="book-open" title="No subjects yet" description="Create the school's subjects first.">
                        @can('setup.subjects.manage')
                            <x-ui.button :href="route('school.setup.subjects.create')" size="sm" icon="plus">New subject</x-ui.button>
                        @endcan
                    </x-ui.empty>
                @else
                    <x-form.form :action="route('school.setup.classes.subjects', $class)" method="PUT" class="space-y-4">
                        <p class="text-sm text-ink-faint">Tick the subjects taught in {{ $class->name }}. Optional subjects are chosen per student; the order is used on report cards.</p>
                        <ul class="divide-y divide-line rounded-xl border border-line">
                            @foreach ($subjects as $subject)
                                @php
                                    $pivot = $mapped->get($subject->id)?->pivot;
                                    $base = 'subjects['.$subject->id.']';
                                    // Only this form's own old input counts (a failed section form must not untick everything).
                                    $row = $subjectsOld !== null ? ($subjectsOld[$subject->id] ?? []) : null;
                                    $selected = $row !== null ? ! empty($row['selected']) : $pivot !== null;
                                    $optional = $row !== null ? ! empty($row['is_optional']) : (bool) $pivot?->is_optional;
                                    $order = $row !== null ? ($row['sort_order'] ?? '') : ($pivot?->sort_order ?? $subject->sort_order);
                                @endphp
                                <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2">
                                    <label class="check min-w-0 flex-1" for="subject-{{ $subject->id }}">
                                        <input type="checkbox" id="subject-{{ $subject->id }}" name="{{ $base }}[selected]" value="1" class="check-input" @checked($selected)>
                                        <span class="check-label">{{ $subject->name }}@if ($subject->code) <span class="text-ink-faint">· {{ $subject->code }}</span>@endif</span>
                                    </label>
                                    <label class="check" for="optional-{{ $subject->id }}">
                                        <input type="checkbox" id="optional-{{ $subject->id }}" name="{{ $base }}[is_optional]" value="1" class="check-input" @checked($optional)>
                                        <span class="check-label">Optional</span>
                                    </label>
                                    <label class="sr-only" for="order-{{ $subject->id }}">Order of {{ $subject->name }}</label>
                                    <input id="order-{{ $subject->id }}" type="number" name="{{ $base }}[sort_order]" min="0" max="999"
                                           value="{{ $order }}" class="input w-20 tabular-nums" inputmode="numeric">
                                </li>
                            @endforeach
                        </ul>
                        <x-form.error name="items" />
                        <x-form.actions>
                            <x-ui.button type="submit" icon="check">Save subjects</x-ui.button>
                        </x-form.actions>
                    </x-form.form>
                @endif
            @elseif ($class->subjects->isEmpty())
                <x-ui.empty icon="book-open" title="No subjects mapped" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($class->subjects as $subject)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-ink">{{ $subject->name }}</span>
                            @if ($subject->pivot->is_optional)<x-ui.badge tone="info">Optional</x-ui.badge>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    @if ($canManage)
        {{-- Add section --}}
        <x-ui.modal name="section-new" title="Add a section" :description="'New section of '.$class->name" :show="$errors->any() && old('section_id') === '0'">
            <x-form.form :action="route('school.setup.sections.store', $class)" id="section-form-new" class="space-y-5">
                <input type="hidden" name="section_id" value="0">
                @include('school.setup.classes._section-fields', ['section' => null, 'teacherOptions' => $teacherOptions])
            </x-form.form>
            <x-slot:footer>
                <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'section-new')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="section-form-new" icon="plus">Add section</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Edit section --}}
        @foreach ($class->sections as $section)
            @php
                $options = $teacherOptions;
                if ($section->classTeacher && ! isset($options[$section->class_teacher_id])) {
                    $options[$section->class_teacher_id] = $section->classTeacher->fullName().' (inactive)';
                }
            @endphp
            <x-ui.modal :name="'section-'.$section->id" :title="'Edit section '.$section->name" :show="$errors->any() && $editingSection === $section->id">
                <x-form.form :action="route('school.setup.sections.update', $section)" method="PUT" :id="'section-form-'.$section->id" class="space-y-5">
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    @include('school.setup.classes._section-fields', ['section' => $section, 'teacherOptions' => $options, 'useOld' => $editingSection === $section->id])
                </x-form.form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'section-{{ $section->id }}')">Cancel</x-ui.button>
                    <x-ui.button type="submit" :form="'section-form-'.$section->id" icon="check">Save section</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach
    @endif
</x-layouts.app>
