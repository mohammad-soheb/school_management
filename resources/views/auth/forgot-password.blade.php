@php
    $route = fn (string $name, string $fallback) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
@endphp
<x-layouts.guest title="Forgot password" heading="Reset your password" subheading="Enter the email address of your account and we will send you a link to choose a new password." :inline-status="true">
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    <x-form.form :action="$route('password.email', '/forgot-password')" method="POST" class="space-y-5">
        <x-form.input name="email" type="email" label="Email address" required autofocus
                      autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="you@school.example" />

        <x-ui.button type="submit" size="lg" block>Email me a reset link</x-ui.button>
    </x-form.form>

    <p class="mt-6 text-center text-sm text-ink-soft">
        <a href="{{ $route('login', '/login') }}" class="auth-link inline-flex items-center gap-1.5">
            <x-ui.icon name="arrow-left" variant="m" class="h-4 w-4" />Back to sign in
        </a>
    </p>
</x-layouts.guest>
