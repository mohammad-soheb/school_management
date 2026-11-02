@php
    $canEdit = auth()->user()->can('update', $homework);
    $tz = config('app.display_timezone');
@endphp
<x-layouts.app :title="$homework->title">
    <x-ui.page-header :title="$homework->title"
        :breadcrumbs="[['label' => 'Homework', 'url' => route('school.homework.index')], ['label' => $homework->title]]">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-ui.badge tone="brand">{{ $homework->subject->name }}</x-ui.badge>
            <x-ui.badge tone="neutral">{{ $homework->audienceLabel() }}</x-ui.badge>
            @if ($homework->isArchived())
                <x-ui.badge tone="neutral" dot>Archived</x-ui.badge>
            @elseif ($homework->isOverdue())
                <x-ui.badge tone="danger" dot>Past due</x-ui.badge>
            @elseif ($homework->isDueToday())
                <x-ui.badge tone="warning" dot>Due today</x-ui.badge>
            @else
                <x-ui.badge tone="success" dot>Open</x-ui.badge>
            @endif
        </div>

        <x-slot:actions>
            @if ($canEdit)
                @if ($homework->isArchived())
                    <x-form.form :action="route('school.homework.restore', $homework)">
                        <x-ui.button type="submit" variant="secondary" icon="arrow-uturn-left">Restore</x-ui.button>
                    </x-form.form>
                @else
                    <x-ui.confirm :action="route('school.homework.archive', $homework)" method="POST" tone="warning"
                                  title="Archive this homework?" message="It disappears from lists, dashboards and the parent portal. You can restore it later."
                                  confirm="Archive">
                        <x-ui.button variant="secondary" icon="archive-box">Archive</x-ui.button>
                    </x-ui.confirm>
                    <x-ui.button :href="route('school.homework.edit', $homework)" icon="pencil-square">Edit</x-ui.button>
                @endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Instructions">
                <div class="comms-body">{!! nl2br(e($homework->description)) !!}</div>
            </x-ui.card>

            <x-ui.card title="Attachments" :padding="$homework->attachments->isEmpty()">
                @if ($homework->attachments->isEmpty())
                    <p class="text-sm text-ink-faint">No files attached.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($homework->attachments as $file)
                            <li class="flex items-center gap-3 px-4 py-3">
                                <span class="stat-icon stat-icon-neutral shrink-0"><x-ui.icon :name="$file->isImage() ? 'photo' : 'document-text'" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ $file->original_name }}</span>
                                    <span class="block text-xs text-ink-faint">{{ strtoupper($file->extension) }} · {{ $file->humanSize() }}</span>
                                </span>
                                <x-ui.button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('school.homework.attachments.show', [$homework, $file])">Download</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Details">
            <x-ui.dl>
                <x-ui.dl-item label="Class">{{ $homework->audienceLabel() }}</x-ui.dl-item>
                <x-ui.dl-item label="Subject">{{ $homework->subject->name }}</x-ui.dl-item>
                <x-ui.dl-item label="Assigned on">{{ $homework->assigned_on->format('l, d M Y') }}</x-ui.dl-item>
                <x-ui.dl-item label="Due on">{{ $homework->due_on->format('l, d M Y') }}</x-ui.dl-item>
                <x-ui.dl-item label="Academic year">{{ $homework->academicYear?->name }}</x-ui.dl-item>
                <x-ui.dl-item label="Set by">{{ $homework->creator?->name }} @if ($homework->created_at)<span class="text-ink-faint">· {{ $homework->created_at->timezone($tz)->format('d M Y, h:i A') }}</span>@endif</x-ui.dl-item>
                @if ($homework->updated_by && $homework->updated_at && ! $homework->updated_at->eq($homework->created_at))
                    <x-ui.dl-item label="Last edited by">{{ $homework->updater?->name }} <span class="text-ink-faint">· {{ $homework->updated_at->timezone($tz)->format('d M Y, h:i A') }}</span></x-ui.dl-item>
                @endif
            </x-ui.dl>
        </x-ui.card>
    </div>
</x-layouts.app>
