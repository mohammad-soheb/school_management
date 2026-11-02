@php
    $route = fn (string $name, string $fallback) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
@endphp
<x-layouts.guest title="Choose a new password" heading="Choose a new password" subheading="Use at least 12 characters. A short phrase of several words is easy to remember and hard to guess.">
    <x-form.form :action="$route('password.update', '/reset-password')" method="POST" class="space-y-5">
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" type="email" label="Email address" :value="$request->email" required
                      autocomplete="username" inputmode="email" autocapitalize="off" spellcheck="false" />

        <x-form.password name="password" label="New password" autocomplete="new-password" required autofocus />

        <x-form.password name="password_confirmation" label="Confirm new password" autocomplete="new-password" required />

        <x-ui.button type="submit" size="lg" block>Reset password</x-ui.button>
    </x-form.form>
</x-layouts.guest>
