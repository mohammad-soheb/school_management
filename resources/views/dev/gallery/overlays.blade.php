<x-dev.section id="overlays" title="Overlays" description="Modals (bottom sheets on phones), confirm dialogs and toasts.">
    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Modal" description="Open with $dispatch('open-modal', name).">
            <x-ui.button icon="user-plus" @click="$dispatch('open-modal', 'gallery-invite')">Invite a user</x-ui.button>

            <x-ui.modal name="gallery-invite" title="Invite a user" description="They will receive an email with a sign-in link." max-width="lg">
                <x-form.form id="gallery-invite-form" action="{{ url('/_ui/slow') }}" method="POST" class="space-y-5">
                    <x-form.input name="invite_name" label="Full name" autofocus required />
                    <x-form.input name="invite_email" type="email" label="Email address" required />
                    <x-form.select name="invite_role" label="Role" :options="['teacher' => 'Teacher', 'accountant' => 'Accountant', 'receptionist' => 'Receptionist']" placeholder="Choose a role" required />
                </x-form.form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'gallery-invite')">Cancel</x-ui.button>
                    <x-ui.button type="submit" form="gallery-invite-form">Send invite</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </x-ui.card>

        <x-ui.card title="Confirm" description="Destructive actions ask first; Cancel has focus.">
            <div class="flex flex-wrap gap-3">
                <x-ui.confirm :action="url('/_ui/slow')" method="POST" title="Mark Aarav Sharma as left?"
                              message="Their history is kept; they will no longer appear in class lists." confirm="Mark as left">
                    <x-ui.button variant="danger" icon="archive-box">Mark as left</x-ui.button>
                </x-ui.confirm>
                <x-ui.confirm :action="url('/_ui/slow')" method="POST" tone="brand" title="Publish results?"
                              message="Parents will see the marks immediately." confirm="Publish">
                    <x-ui.button variant="secondary" icon="megaphone">Publish</x-ui.button>
                </x-ui.confirm>
            </div>
        </x-ui.card>

        <x-ui.card title="Toasts" description="Flash keys from the server, or window.toast() from scripts.">
            <div class="flex flex-wrap gap-2">
                <x-ui.button size="sm" variant="secondary" @click="window.toast('Receipt R-2026-1042 saved.', 'success')">Success</x-ui.button>
                <x-ui.button size="sm" variant="secondary" @click="window.toast('Results go live on 5 November.', 'info')">Info</x-ui.button>
                <x-ui.button size="sm" variant="secondary" @click="window.toast('Three instalments are overdue.', 'warning')">Warning</x-ui.button>
                <x-ui.button size="sm" variant="secondary" @click="window.toast('The import failed.', 'danger', 'Something went wrong')">Danger</x-ui.button>
            </div>
        </x-ui.card>
    </div>
</x-dev.section>
