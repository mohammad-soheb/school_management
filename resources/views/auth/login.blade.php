@php
    $route = fn (string $name, string $fallback) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
@endphp
<x-layouts.guest title="Sign in" heading="Welcome back" subheading="Sign in to continue to your school workspace.">
    <x-form.form :action="$route('login', '/login')" method="POST" class="space-y-5">
        <x-form.input name="email" type="email" label="Email address" required autofocus
                      autocomplete="username" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="you@school.example" />

        <x-form.password name="password" label="Password" autocomplete="current-password" required>
            @if (\Illuminate\Support\Facades\Route::has('password.request'))
                <x-slot:aside>
                    <a href="{{ route('password.request') }}" class="auth-link text-sm">Forgot password?</a>
                </x-slot:aside>
            @endif
        </x-form.password>

        <x-form.checkbox name="remember" label="Keep me signed in on this device" />

        <x-ui.button type="submit" size="lg" block>Sign in</x-ui.button>
    </x-form.form>
</x-layouts.guest>
