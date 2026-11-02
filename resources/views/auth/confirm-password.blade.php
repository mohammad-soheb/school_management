@php
    $route = fn (string $name, string $fallback) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
@endphp
<x-layouts.guest title="Confirm password" heading="Confirm your password" subheading="This is a secure area. Please confirm your password before continuing.">
    <x-form.form :action="$route('password.confirm', '/user/confirm-password')" method="POST" class="space-y-5">
        <x-form.password name="password" label="Password" autocomplete="current-password" required autofocus />

        <x-ui.button type="submit" size="lg" block>Confirm</x-ui.button>
    </x-form.form>
</x-layouts.guest>
