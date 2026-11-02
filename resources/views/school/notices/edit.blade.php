<x-layouts.app :title="'Edit: '.$notice->title">
    <x-ui.page-header title="Edit notice" :description="$notice->title"
        :breadcrumbs="[
            ['label' => 'Notices', 'url' => route('school.notices.manage')],
            ['label' => $notice->title, 'url' => route('school.notices.show', $notice)],
            ['label' => 'Edit'],
        ]">
        <div class="mt-2"><x-ui.badge :tone="$notice->stateTone()" dot>{{ $notice->stateLabel() }}</x-ui.badge></div>
    </x-ui.page-header>

    @if ($notice->state() === 'live')
        <x-ui.alert tone="info" class="mb-6">This notice is live: changes show on the board as soon as you save.</x-ui.alert>
    @endif

    <x-form.form :action="route('school.notices.update', $notice)" method="PUT" :files="true" class="space-y-6">
        @include('school.notices._form', ['notice' => $notice])

        <x-form.actions sticky>
            <x-ui.button :href="route('school.notices.show', $notice)" variant="ghost">Cancel</x-ui.button>
            @if ($notice->status === \App\Enums\Communication\NoticeStatus::Draft)
                <x-ui.button type="submit" name="action" value="draft" variant="secondary" icon="document">Save draft</x-ui.button>
                <x-ui.button type="submit" name="action" value="publish" icon="megaphone">Save and publish</x-ui.button>
            @else
                <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
            @endif
        </x-form.actions>
    </x-form.form>
</x-layouts.app>
