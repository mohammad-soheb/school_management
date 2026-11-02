<x-layouts.platform :title="$school->name">
    <x-ui.page-header :title="$school->name" :description="'Code '.$school->code"
                      :breadcrumbs="[['label' => 'Schools', 'url' => route('platform.schools.index')], ['label' => $school->name]]">
        <x-ui.badge :tone="$school->status->tone()" dot>{{ $school->status->label() }}</x-ui.badge>
        <x-slot:actions>
            <x-ui.button :href="route('platform.schools.edit', $school)" variant="secondary" icon="pencil-square">Edit</x-ui.button>
            @if ($school->isActive())
                <x-ui.button variant="danger" icon="pause-circle" @click="$dispatch('open-modal', 'suspend-school')">Suspend</x-ui.button>
            @else
                <x-form.form :action="route('platform.schools.activate', $school)">
                    <x-ui.button type="submit" variant="success" icon="play-circle">Activate</x-ui.button>
                </x-form.form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('invitation_url'))
        <x-ui.alert tone="success" title="School Admin invited" class="mb-6" dismissible>
            <p class="mb-3">Share this one-time sign-up link if the email does not arrive:</p>
            <div class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 break-all rounded-lg bg-surface-muted px-3 py-2 font-mono text-[13px]">{{ session('invitation_url') }}</code>
                <x-ui.copy :text="session('invitation_url')" label="Copy link" />
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Details" class="lg:col-span-2">
            <x-ui.dl grid>
                <x-ui.dl-item label="Board">{{ $school->board ?? '—' }}</x-ui.dl-item>
                <x-ui.dl-item label="Office email">{{ $school->email ?? '—' }}</x-ui.dl-item>
                <x-ui.dl-item label="Office phone">{{ $school->phone ?? '—' }}</x-ui.dl-item>
                <x-ui.dl-item label="City">{{ collect([$school->city, $school->state, $school->pincode])->filter()->join(', ') ?: '—' }}</x-ui.dl-item>
                <x-ui.dl-item label="Address">{{ collect([$school->address_line1, $school->address_line2])->filter()->join(', ') ?: '—' }}</x-ui.dl-item>
                <x-ui.dl-item label="Added">{{ $school->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</x-ui.dl-item>
            </x-ui.dl>
        </x-ui.card>

        <x-ui.card title="Access" description="Suspending blocks every user of this school. No data is deleted.">
            <p class="text-sm text-ink-soft">Users: {{ $school->memberships()->count() }}</p>
        </x-ui.card>
    </div>

    {{-- Drop-in panels: a module adds resources/views/platform/schools/panels/NN-name.blade.php (receives $school). --}}
    @foreach (glob(resource_path('views/platform/schools/panels/*.blade.php')) ?: [] as $panel)
        <div class="mt-6">@include('platform.schools.panels.'.basename($panel, '.blade.php'), ['school' => $school])</div>
    @endforeach

    <x-ui.modal name="suspend-school" title="Suspend this school?" description="Its users are signed out of this school until you activate it again." :show="$errors->has('reason')">
        <x-form.form :action="route('platform.schools.suspend', $school)" id="suspend-form">
            <x-form.textarea name="reason" label="Reason (recorded in the audit log)" rows="3" required />
        </x-form.form>
        <x-slot:footer>
            <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'suspend-school')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="suspend-form" variant="danger">Suspend school</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-layouts.platform>
