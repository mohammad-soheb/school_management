@php
    $blocking = collect($warnings)->contains(fn ($w) => $w['tone'] === 'danger');
    $continuing = ($totals['promote'] ?? 0) + ($totals['retain'] ?? 0);
    $leaving = ($totals['graduate'] ?? 0) + ($totals['transfer'] ?? 0) + ($totals['withdraw'] ?? 0);
@endphp
<x-layouts.app :title="'Confirm run #'.$run->id">
    <x-ui.page-header :title="'Confirm promotion run #'.$run->id"
                      description="Step 3 of 3: check the summary. Committing changes every student below in one step."
                      :breadcrumbs="[['label' => 'Year-end promotion', 'url' => route('school.promotion.index')], ['label' => 'Run #'.$run->id, 'url' => route('school.promotion.show', $run)], ['label' => 'Confirm']]" />

    @if ($errors->any())
        <x-ui.alert tone="danger" title="The run was not committed" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Summary">
                <x-ui.dl>
                    <x-ui.dl-item label="From">{{ $run->fromYear->name }}</x-ui.dl-item>
                    <x-ui.dl-item label="To">{{ $run->toYear->name }}</x-ui.dl-item>
                    <x-ui.dl-item label="Classes">{{ $classes->map(fn ($c) => $c['class']->name.' ('.$c['total'].')')->implode(', ') }}</x-ui.dl-item>
                    <x-ui.dl-item label="Continue next year">{{ $continuing }} &middot; {{ $totals['promote'] ?? 0 }} promoted, {{ $totals['retain'] ?? 0 }} retained</x-ui.dl-item>
                    <x-ui.dl-item label="Leave the school">{{ $leaving }} &middot; {{ $totals['graduate'] ?? 0 }} graduated, {{ $totals['transfer'] ?? 0 }} transferred, {{ $totals['withdraw'] ?? 0 }} withdrawn</x-ui.dl-item>
                    <x-ui.dl-item label="Skipped">{{ $totals['skipped'] ?? 0 }}</x-ui.dl-item>
                    <x-ui.dl-item label="Unpaid dues">{{ $run->option('carry_forward_dues', true) ? 'Carried forward as opening balances' : 'Not carried forward' }}</x-ui.dl-item>
                    <x-ui.dl-item label="Source year">{{ $run->option('lock_source_year') ? 'Locked after commit (if it is no longer the current year)' : 'Left open' }}</x-ui.dl-item>
                </x-ui.dl>
            </x-ui.card>

            @foreach ($warnings as $warning)
                <x-ui.alert :tone="$warning['tone']" :title="$warning['title']">{{ $warning['message'] }}</x-ui.alert>
            @endforeach
        </div>

        <x-ui.card title="What happens">
            <ul class="space-y-3 text-sm text-ink-soft">
                <li class="flex gap-2"><x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-success-ink" />Promoted and retained students get a new enrollment in {{ $run->toYear->name }}.</li>
                <li class="flex gap-2"><x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-success-ink" />Their {{ $run->fromYear->name }} enrollment is closed; its attendance, fees, marks and receipts stay unchanged.</li>
                <li class="flex gap-2"><x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-success-ink" />Graduated, transferred and withdrawn students are marked as such on their record.</li>
                <li class="flex gap-2"><x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 text-success-ink" />Committing twice changes nothing the second time.</li>
            </ul>

            <x-slot:footer>
                <x-form.form :action="route('school.promotion.commit', $run)" class="space-y-4">
                    <x-form.checkbox name="confirm" label="I have reviewed every class in this run" />
                    <x-form.actions>
                        <x-ui.button :href="route('school.promotion.show', $run)" variant="secondary">Back to preview</x-ui.button>
                        <x-ui.button type="submit" variant="success" icon="check" :disabled="$blocking">Commit promotion</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            </x-slot:footer>
        </x-ui.card>
    </div>
</x-layouts.app>
