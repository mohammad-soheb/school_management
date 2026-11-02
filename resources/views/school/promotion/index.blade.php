@php
    $canRun = auth()->user()->can('promotion.run');
    $canReopen = auth()->user()->can('promotion.reopen');
@endphp
<x-layouts.app title="Year-end promotion">
    <x-ui.page-header title="Year-end promotion"
                      description="Move students into the next academic year: promote, retain, graduate, transfer or withdraw. Last year's records stay exactly as they were.">
        <x-slot:actions>
            @if ($canRun)
                <x-ui.button :href="route('school.promotion.create')" icon="plus">New promotion run</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" title="That did not work" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-ui.card :padding="false" title="Promotion runs" description="Each run covers some classes of one year. Drafts can be edited until they are committed." class="xl:col-span-2">
            @if ($runs->isEmpty())
                <x-ui.empty icon="arrow-trending-up" title="No promotion runs yet"
                            description="At the end of the year, start a run to prepare next year's classes. Nothing changes until you confirm it.">
                    @if ($canRun)
                        <x-ui.button :href="route('school.promotion.create')" icon="plus">New promotion run</x-ui.button>
                    @endif
                </x-ui.empty>
            @else
                <x-ui.table caption="Promotion runs">
                    <x-slot:head>
                        <x-ui.th>Run</x-ui.th>
                        <x-ui.th>Years</x-ui.th>
                        <x-ui.th class="num">Students</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </x-slot:head>
                    @foreach ($runs as $run)
                        <tr>
                            <td>
                                <p class="font-medium text-ink">#{{ $run->id }}</p>
                                <p class="text-sm text-ink-faint">by {{ $run->creator?->name ?? 'unknown' }}, {{ $run->created_at->diffForHumans() }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ $run->fromYear->name }} <span class="text-ink-faint">&rarr;</span> {{ $run->toYear->name }}</td>
                            <td class="num">{{ $run->items_count }}</td>
                            <td>
                                <x-ui.badge :tone="$run->status->tone()" dot>{{ $run->status->label() }}</x-ui.badge>
                                @if ($run->isCommitted())
                                    <p class="mt-1 text-xs text-ink-faint">{{ $run->committed_at?->diffForHumans() }}</p>
                                @endif
                            </td>
                            <td class="col-actions">
                                <x-ui.button :href="route('school.promotion.show', $run)" variant="ghost" size="sm" icon-right="chevron-right">{{ $run->isDraft() ? 'Continue' : 'Open' }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div class="px-4 py-3">{{ $runs->links() }}</div>
            @endif
        </x-ui.card>

        <x-ui.card :padding="false" title="Academic years" description="A locked year is read-only. Reopen it only to correct mistakes.">
            @if ($years->isEmpty())
                <x-ui.empty icon="calendar-days" title="No academic years" description="Create academic years in school setup first." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($years as $year)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-ink">
                                    {{ $year->name }}
                                    @if ($year->is_current)<x-ui.badge tone="brand" class="ml-1">Current</x-ui.badge>@endif
                                </p>
                                <p class="text-sm text-ink-faint">{{ $year->starts_on->format('d M Y') }} &ndash; {{ $year->ends_on->format('d M Y') }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.badge :tone="$year->status->tone()" dot>{{ $year->status->label() }}</x-ui.badge>
                                @if ($canReopen && $year->isLocked())
                                    <x-ui.button variant="ghost" size="sm" icon="lock-open" @click="$dispatch('open-modal', 'reopen-{{ $year->id }}')">Reopen</x-ui.button>
                                    <x-ui.modal :name="'reopen-'.$year->id" :title="'Reopen '.$year->name.'?'" description="Reopening lets you correct promotions and records of this year. The reason is kept in the audit log.">
                                        <x-form.form :action="route('school.promotion.years.reopen', $year)" id="reopen-form-{{ $year->id }}" class="space-y-4">
                                            <x-form.textarea name="reason" label="Reason" rows="3" required maxlength="255" :id="'reopen-reason-'.$year->id"
                                                             hint="For example: Class 5 student wrongly promoted, needs to be retained." />
                                        </x-form.form>
                                        <x-slot:footer>
                                            <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'reopen-{{ $year->id }}')">Cancel</x-ui.button>
                                            <x-ui.button type="submit" form="reopen-form-{{ $year->id }}" variant="danger" icon="lock-open">Reopen year</x-ui.button>
                                        </x-slot:footer>
                                    </x-ui.modal>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
