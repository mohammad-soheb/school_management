<x-layouts.guest title="No access" heading="No school linked yet" subheading="You are signed in, but your account is not linked to a school.">
    <p class="text-sm text-ink-soft">
        Ask your school administrator to send you an invitation, or contact support if you think this is a mistake.
    </p>

    <x-form.form :action="route('logout')" class="mt-6">
        <x-ui.button type="submit" variant="secondary" block>Sign out</x-ui.button>
    </x-form.form>
</x-layouts.guest>
