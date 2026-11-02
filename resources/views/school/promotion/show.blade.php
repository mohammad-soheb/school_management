@php
    $user = auth()->user();
    $editable = $run->isDraft() && $user->can('update', $run);
    $canCorrect = $run->isCommitted() && $user->can('correct', $run);
    $outcomes = \App\Enums\Promotion\PromotionOutcome::options();
    $pendingIds = $items->filter(fn ($i) => $i->isPending())->pluck('id')->all();
    $hasErrors = $errors->any();
@endphp
<x-layouts.app :title="'Promotion run #'.$run->id">
    <x-ui.page-header :title="'Promotion run #'.$run->id"
                      :description="$run->fromYear->name.' → '.$run->toYear->name"
                      :breadcrumbs="[['label' => 'Year-end promotion', 'url' => route('school.promotion.index')], ['label' => 'Run #'.$run->id]]">
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-ink-soft">
            <x-ui.badge :tone="$run->status->tone()" dot>{{ $run->status->label() }}</x-ui.badge>
            <span>Prepared by {{ $run->creator?->name ?? 'unknown' }} {{ $run->created_at->diffForHumans() }}</span>
            @if ($run->isCommitted())
                <span>&middot; committed by {{ $run->committer?->name ?? 'unknown' }} on {{ $run->committed_at?->timezone(config('app.display_timezone'))->format('d M Y, H:i') }}</span>
            @endif
        </div>
        <x-slot:actions>
            @if ($editable)
                <x-ui.confirm :action="route('school.promotion.cancel', $run)" method="POST" title="Cancel this draft?"
                              message="Nothing has been changed for any student yet. The draft is kept in the list as cancelled." confirm="Cancel draft" cancel="Keep it">
                    <x-ui.button variant="ghost" icon="x-circle">Cancel draft</x-ui.button>
                </x-ui.confirm>
                <x-ui.button :href="route('school.promotion.confirm', $run)" icon-right="arrow-right">Review &amp; commit</x-ui.button>
            @endif
            @if ($run->isCommitted() && $user->can('update', $run) && ! $run->fromYear->isLocked())
                <x-ui.confirm :action="route('school.promotion.lock-source', $run)" method="POST" :title="'Lock '.$run->fromYear->name.'?'"
                              message="The year becomes read-only. Holders of the reopen permission can reopen it with a reason." confirm="Lock year" tone="warning">
                    <x-ui.button variant="secondary" icon="lock-closed">Lock {{ $run->fromYear->name }}</x-ui.button>
                </x-ui.confirm>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($hasErrors)
        <x-ui.alert tone="danger" title="Some changes were not saved" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach (array_unique($errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- Totals --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach (\App\Enums\Promotion\PromotionOutcome::cases() as $case)
            <x-ui.stat :label="$case->label()" :value="number_format($totals[$case->value] ?? 0)" :tone="$case->tone()" />
        @endforeach
        <x-ui.stat label="Skipped" :value="number_format($totals['skipped'] ?? 0)" tone="neutral" />
    </div>

    @if ($run->isCommitted())
        <x-ui.alert tone="success" title="Committed" class="mb-6">
            New enrollments were created in {{ $run->toYear->name }} and the {{ $run->fromYear->name }} enrollments were closed. Their attendance, fees and marks stay with {{ $run->fromYear->name }}.
            @if ($run->count('dues_carried_paise') > 0)
                Unpaid dues carried forward: <strong>@money($run->count('dues_carried_paise'))</strong>.
            @endif
            @if ($run->count('reverted') + $run->count('corrected') > 0)
                Corrections since: {{ $run->count('corrected') }} changed, {{ $run->count('reverted') }} reverted.
            @endif
        </x-ui.alert>
    @elseif ($run->status->value === 'cancelled')
        <x-ui.alert tone="neutral" class="mb-6">This draft was cancelled. Nothing was changed for any student.</x-ui.alert>
    @endif

    @foreach ($warnings as $warning)
        <x-ui.alert :tone="$warning['tone']" :title="$warning['title']" class="mb-3">{{ $warning['message'] }}</x-ui.alert>
    @endforeach

    @if ($classes->isEmpty())
        <x-ui.card class="mt-6"><x-ui.empty icon="users" title="No students in this run" /></x-ui.card>
    @else
        <x-ui.tabs class="mt-6" label="Classes in this run" :tabs="$classes->map(fn ($c) => [
            'label' => $c['class']->name,
            'url' => route('school.promotion.show', [$run, 'class' => $c['class']->id]),
            'active' => $c['class']->id === $classId,
            'badge' => $c['total'],
        ])->all()" />

        @if ($editable && $pendingIds !== [])
            <x-ui.card class="mt-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <x-form.form :action="route('school.promotion.items.bulk', $run)" id="bulk-form" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <x-form.select name="action" label="Selected students" class="sm:w-56" id="bulk-action"
                                       :options="$outcomes + ['default' => 'Reset to default']" :selected="'retain'" />
                        <x-ui.button type="submit" variant="secondary" icon="check">Apply to selected</x-ui.button>
                    </x-form.form>
                    <x-form.form :action="route('school.promotion.items.bulk', $run)" class="flex">
                        <input type="hidden" name="action" value="promote">
                        @foreach ($pendingIds as $id)
                            <input type="hidden" name="item_ids[]" value="{{ $id }}">
                        @endforeach
                        <x-ui.button type="submit" variant="ghost" icon="arrow-trending-up">Promote whole class</x-ui.button>
                    </x-form.form>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card :padding="false" class="mt-4">
            @if ($editable)
                <x-form.form :action="route('school.promotion.items.update', $run)" method="PUT" id="items-form">
                    @include('school.promotion.partials.items-table', ['editable' => true])
                    <x-form.actions sticky class="border-t border-line px-4 py-3">
                        <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            @else
                @include('school.promotion.partials.items-table', ['editable' => false])
            @endif
        </x-ui.card>
    @endif
</x-layouts.app>
