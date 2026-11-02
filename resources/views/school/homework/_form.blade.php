{{--
    Homework form (create + edit). Receives: $options (HomeworkAccess::formOptions), $subjects (id => name),
    $year (AcademicYear), optional $homework. The class -> section -> subject pickers only offer what this user may
    set; the server re-checks every submission (HomeworkService), so the pickers are a convenience, not security.
--}}
@php
    $homework ??= null;
    $initial = [
        'classId' => (string) old('school_class_id', $homework?->school_class_id ?? ''),
        'sectionId' => (string) old('section_id', $homework?->section_id ?? ''),
        'subjectId' => (string) old('subject_id', $homework?->subject_id ?? ''),
    ];
    $today = school_today()->toDateString();
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-ui.card title="Class and subject" description="Choose a section, or the whole class to set it for every section.">
            <div class="grid gap-4 sm:grid-cols-3"
                 x-data="{
                     options: @js($options),
                     subjectNames: @js((object) $subjects),
                     classId: @js($initial['classId']),
                     sectionId: @js($initial['sectionId']),
                     subjectId: @js($initial['subjectId']),
                     get current() { return this.options.find(c => String(c.id) === String(this.classId)) ?? null },
                     get sections() { return this.current ? this.current.sections : [] },
                     get allowWhole() { return !! this.current && this.current.whole_class_subjects.length > 0 },
                     get subjectIds() {
                         if (! this.current) return [];
                         if (this.sectionId === '') return this.current.whole_class_subjects;
                         const s = this.current.sections.find(s => String(s.id) === String(this.sectionId));
                         return s ? s.subjects : [];
                     },
                     resetSection() {
                         this.sectionId = this.allowWhole ? '' : (this.sections[0] ? String(this.sections[0].id) : '');
                         this.resetSubject();
                     },
                     resetSubject() {
                         if (! this.subjectIds.map(String).includes(String(this.subjectId))) this.subjectId = '';
                     },
                 }">
                <x-form.select name="school_class_id" label="Class" placeholder="Choose a class" required
                               :options="collect($options)->pluck('name', 'id')->all()" :selected="$initial['classId']"
                               x-model="classId" x-on:change="resetSection()" />

                <x-form.select name="section_id" label="Section" x-model="sectionId" x-on:change="resetSubject()" x-bind:disabled="! current">
                    <template x-if="allowWhole || ! current">
                        <option value="" :selected="sectionId === ''">Whole class (all sections)</option>
                    </template>
                    <template x-for="s in sections" :key="s.id">
                        <option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === String(sectionId)"></option>
                    </template>
                </x-form.select>

                <x-form.select name="subject_id" label="Subject" required x-model="subjectId" x-bind:disabled="subjectIds.length === 0">
                    <option value="">Choose a subject</option>
                    <template x-for="id in subjectIds" :key="id">
                        <option :value="String(id)" x-text="subjectNames[id] ?? ('Subject ' + id)" :selected="String(id) === String(subjectId)"></option>
                    </template>
                </x-form.select>
            </div>
        </x-ui.card>

        <x-ui.card title="Homework">
            <div class="space-y-4">
                <x-form.input name="title" label="Title" :value="$homework?->title" maxlength="150" required placeholder="e.g. Exercise 4.2, questions 1 to 10" />
                <x-form.textarea name="description" label="Instructions" :value="$homework?->description" rows="7" maxlength="5000" required
                                 hint="Plain text. Line breaks are kept; links and formatting are not." />
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card title="Dates">
            <div class="space-y-4">
                <x-form.date name="assigned_on" label="Assigned on" :value="$homework?->assigned_on?->toDateString() ?? $today" required
                             min="{{ $year?->starts_on?->toDateString() }}" max="{{ $year?->ends_on?->toDateString() }}" />
                <x-form.date name="due_on" label="Due on" :value="$homework?->due_on?->toDateString() ?? school_today()->addDay()->toDateString()" required
                             hint="Cannot be before the assigned date." />
            </div>
        </x-ui.card>

        <x-ui.card title="Attachments" :description="'Optional. Up to '.\App\Services\Communication\HomeworkService::MAX_ATTACHMENTS.' files, 5 MB each.'">
            @if ($homework && $homework->attachments->isNotEmpty())
                <ul class="comms-files mb-4">
                    @foreach ($homework->attachments as $file)
                        <li class="comms-file">
                            <x-ui.icon name="paper-clip" class="h-4 w-4 shrink-0 text-ink-faint" />
                            <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $file->original_name }}</span>
                            <x-form.checkbox name="remove_attachments[]" :value="$file->id" label="Remove" class="!mb-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-form.file name="attachments" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                         hint="PDF, Word, Excel, PowerPoint, JPG or PNG. Choose the files again if the form shows an error." />
        </x-ui.card>
    </div>
</div>
