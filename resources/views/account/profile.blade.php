<x-account.layout title="My profile">
    <x-ui.page-header title="My profile" description="Your name and contact number as shown to your school." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Personal details" class="lg:col-span-2">
            <x-form.form :action="route('account.profile.update')" method="PUT" class="space-y-5">
                <x-form.input name="name" label="Full name" :value="$user->name" required autocomplete="name" />
                <x-form.input name="email" label="Email address" type="email" :value="$user->email" disabled hint="Your email is your sign-in. Ask your school administrator to change it." />
                <x-form.input name="phone" label="Mobile number" type="tel" :value="$user->phone" autocomplete="tel" />

                <x-form.actions>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </x-form.actions>
            </x-form.form>
        </x-ui.card>

        <x-ui.card title="Sign-in & security" description="Password and two-factor authentication.">
            <x-ui.button :href="route('account.security')" variant="secondary" icon="shield-check" block>Open security settings</x-ui.button>
            <p class="mt-4 text-sm text-ink-soft">
                Last signed in
                {{ $user->last_login_at ? $user->last_login_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') : 'just now' }}.
            </p>
        </x-ui.card>
    </div>
</x-account.layout>
