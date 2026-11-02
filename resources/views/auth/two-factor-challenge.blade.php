@php
    $route = fn (string $name, string $fallback) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
@endphp
<x-layouts.guest title="Two-factor verification" heading="Two-factor verification" subheading="Confirm it is you to finish signing in.">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <x-form.form :action="$route('two-factor.login', '/two-factor-challenge')" method="POST" class="space-y-5">
            {{-- Authenticator app code --}}
            <div x-show="!recovery" @if ($errors->has('recovery_code')) x-cloak @endif>
                <x-form.input name="code" label="Authentication code" hint="Open your authenticator app and enter the 6-digit code."
                              inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="123456"
                              input-class="text-center text-lg font-semibold tracking-[0.5em] tabular-nums"
                              x-bind:disabled="recovery" x-ref="code" autofocus />
            </div>

            {{-- Recovery code --}}
            <div x-show="recovery" @if (! $errors->has('recovery_code')) x-cloak @endif>
                <x-form.input name="recovery_code" label="Recovery code" hint="Each recovery code works once. Enter one of the codes you saved when you set up two-factor authentication."
                              autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="xxxxx-xxxxx"
                              x-bind:disabled="!recovery" x-ref="recovery" />
            </div>

            <noscript>
                <x-form.input name="recovery_code" id="f-recovery-code-nojs" label="Recovery code (instead of the code above)" autocomplete="off" />
            </noscript>

            <x-ui.button type="submit" size="lg" block>Verify and sign in</x-ui.button>
        </x-form.form>

        <p class="mt-6 text-center text-sm text-ink-soft">
            <button type="button" class="auth-link" x-show="!recovery" @click="recovery = true; $nextTick(() => $refs.recovery.focus())">Use a recovery code instead</button>
            <button type="button" class="auth-link" x-show="recovery" x-cloak @click="recovery = false; $nextTick(() => $refs.code.focus())">Use an authentication code instead</button>
        </p>
    </div>
</x-layouts.guest>
