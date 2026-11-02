@php
    $canAuthor = auth()->user()->can('homework.author');
    $archived = ($filters['status'] ?? null) === 'archived';
    $dueOptions = ['today' => 'Due today', 'week' => 'Due this week', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue'];
    $tabUrl = fn (?string $status) => route('school.homework.index', array_filter(['status' => $status]));
@endphp
<x-layouts.app title="Homework">
    <x-ui.page-header title="Homework" description="Assignments set for classes and sections this academic year.">
        <x-slot:actions>
            @if ($canAuthor)
                <x-ui.button :href="route('school.homework.create')" icon="plus">Set homework</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($canAuthor)
        <x-ui.tabs pill class="mb-4" label="Homework status" :tabs="[
            ['label' => 'Active', 'url' => $tabUrl(null), 'active' => ! $archived, 'icon' => 'book-open'],
            ['label' => 'Archived', 'url' => $tabUrl('archived'), 'active' => $archived, 'icon' => 'archive-box'],
        ]" />
    @endif

    <x-ui.filters :reset="route('school.homework.index', array_filter(['status' => $filters['status'] ?? null]))" placeholder="Search by title">
        @if ($archived)
            <input type="hidden" name="status" value="archived">
        @endif
        <x-form.select name="class_id" label="Class" placeholder="All classes" :options="$options['classes']" :selected="$filters['class_id']" onchange="this.form.section_id && (this.form.section_id.value = ''); this.form.requestSubmit()" />
        @if ($options['sections'] !== [])
            <x-form.select name="section_id" label="Section" placeholder="All sections" :options="$options['sections']" :selected="$filters['section_id']" />
        @endif
        <x-form.select name="subject_id" label="Subject" placeholder="All subjects" :options="$options['subjects']" :selected="$filters['subject_id']" />
        <x-form.select name="due" label="Due" placeholder="Any due date" :options="$dueOptions" :selected="$filters['due'] ?? null" />
        @if ($canAuthor && ! $archived)
            <x-form.checkbox name="mine" label="Only my classes" :checked="$filters['mine']" class="self-end" />
        @endif
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($homework->isEmpty())
            @if ($archived)
                <x-ui.empty icon="archive-box" title="No archived homework" description="Archived homework you can restore appears here." />
            @elseif (collect($filters)->except(['status', 'sort', 'dir'])->filter()->isNotEmpty())
                <x-ui.empty icon="funnel" title="No homework matches these filters" description="Try a different class, subject or due date.">
                    <x-ui.button :href="route('school.homework.index')" variant="secondary">Clear filters</x-ui.button>
                </x-ui.empty>
            @else
                <x-ui.empty icon="book-open" title="No homework yet" description="Homework set for a class or section appears here, and on the parent portal.">
                    @if ($canAuthor)
                        <x-ui.button :href="route('school.homework.create')" icon="plus">Set homework</x-ui.button>
                    @endif
                </x-ui.empty>
            @endif
        @else
            <x-ui.table caption="Homework">
                <x-slot:head>
                    <x-ui.th column="title" label="Homework" />
                    <x-ui.th>Class</x-ui.th>
                    <x-ui.th>Subject</x-ui.th>
                    <x-ui.th column="assigned_on" label="Assigned" />
                    <x-ui.th column="due_on" label="Due" />
                    <x-ui.th>Set by</x-ui.th>
                </x-slot:head>

                @foreach ($homework as $item)
                    <tr>
                        <td class="max-w-xs">
                            <a href="{{ route('school.homework.show', $item) }}" class="font-medium text-ink hover:text-brand-ink">{{ $item->title }}</a>
                            @if ($item->attachments_count > 0)
                                <span class="ml-1 inline-flex items-center gap-0.5 text-xs text-ink-faint" title="{{ $item->attachments_count }} attachment(s)">
                                    <x-ui.icon name="paper-clip" class="h-3.5 w-3.5" />{{ $item->attachments_count }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $item->audienceLabel() }}</td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $item->subject->name }}</td>
                        <td class="whitespace-nowrap text-ink-soft">{{ $item->assigned_on->format('d M') }}</td>
                        <td class="whitespace-nowrap">
                            <span class="text-ink">{{ $item->due_on->format('d M Y') }}</span>
                            @if ($item->isArchived())
                                <x-ui.badge tone="neutral" class="ml-1">Archived</x-ui.badge>
                            @elseif ($item->isOverdue())
                                <x-ui.badge tone="danger" class="ml-1">Past due</x-ui.badge>
                            @elseif ($item->isDueToday())
                                <x-ui.badge tone="warning" class="ml-1">Today</x-ui.badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-sm text-ink-soft">{{ $item->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($homework->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $homework->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
