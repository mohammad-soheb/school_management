<x-layouts.guest title="Invitation not valid" heading="This invitation can't be used" subheading="The link has expired, was already used, or was withdrawn.">
    <p class="text-sm text-ink-soft">Ask your school administrator to send you a new invitation.</p>

    <x-ui.button :href="route('login')" variant="secondary" class="mt-6" block>Go to sign in</x-ui.button>
</x-layouts.guest>
