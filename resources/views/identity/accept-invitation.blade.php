<x-layouts.guest title="Accept invitation" :heading="'Join '.$invitation->school->name" :subheading="'You have been invited as '.$invitation->email.'.'">
    <x-form.form :action="route('invitations.accept', $token)" class="space-y-5">
        @if ($hasAccount)
            <x-ui.alert tone="info">You already have a login with this email. Confirm to add {{ $invitation->school->name }} to your account.</x-ui.alert>
            <x-ui.button type="submit" block>Join {{ $invitation->school->name }}</x-ui.button>
        @else
            <x-form.input name="name" label="Your full name" required autocomplete="name" autofocus />
            <x-form.password name="password" label="Choose a password" required autocomplete="new-password" hint="At least 8 characters." />
            <x-form.password name="password_confirmation" label="Confirm password" required autocomplete="new-password" />
            <x-ui.button type="submit" block>Create my account</x-ui.button>
        @endif
    </x-form.form>
</x-layouts.guest>
