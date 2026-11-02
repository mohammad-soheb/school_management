<x-account.layout title="Security">
    <x-ui.page-header title="Sign-in & security" description="Keep your account safe: a strong password and a second step at sign-in." />

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Two-factor authentication --}}
        <x-ui.card title="Two-factor authentication" description="A code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy)." id="two-factor">
            <x-slot:actions>
                @if ($twoFactor['confirmed'])
                    <x-ui.badge tone="success" dot>Enabled</x-ui.badge>
                @elseif ($twoFactor['pending'])
                    <x-ui.badge tone="warning" dot>Finish setup</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral" dot>Off</x-ui.badge>
                @endif
            </x-slot:actions>

            @if ($twoFactor['required'] && ! $twoFactor['confirmed'])
                <x-ui.alert tone="warning" title="Required for your account" class="mb-5">
                    Platform administrators must use two-factor authentication. Finish the setup below to continue.
                </x-ui.alert>
            @endif

            @if (! $twoFactor['enabled'])
                <p class="mb-4 text-sm text-ink-soft">
                    After you enable this, signing in asks for a 6-digit code from your phone as well as your password.
                    You will be asked to confirm your password first.
                </p>
                <x-form.form action="/user/two-factor-authentication">
                    <x-ui.button type="submit" icon="device-phone-mobile">Enable two-factor authentication</x-ui.button>
                </x-form.form>
            @elseif ($twoFactor['pending'])
                <ol class="mb-5 list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                    <li>Open your authenticator app and scan this QR code.</li>
                    <li>Enter the 6-digit code the app shows to finish setup.</li>
                </ol>

                <div class="mb-5 flex flex-col items-center gap-4 rounded-2xl border border-line bg-surface-muted p-4 sm:flex-row sm:items-start">
                    {{-- The QR code always sits on white (scanners need the contrast), also in dark mode. --}}
                    <div class="size-44 shrink-0 rounded-xl bg-white p-2 shadow-card [&>svg]:size-full" role="img" aria-label="QR code to scan with your authenticator app">{!! $twoFactor['qrSvg'] !!}</div>
                    <div class="min-w-0 text-sm text-ink-soft">
                        <p class="font-medium text-ink">Can't scan?</p>
                        <p class="mt-1">Enter this key in your app instead:</p>
                        <code class="mt-2 block break-all rounded-lg border border-line bg-surface px-3 py-2 font-mono text-[13px] tracking-wider text-ink">{{ $twoFactor['secret'] }}</code>
                    </div>
                </div>

                <x-form.form action="/user/confirmed-two-factor-authentication" class="space-y-4">
                    <x-form.input name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required hint="The code changes every 30 seconds." />
                    <x-form.actions>
                        <x-ui.button type="submit">Confirm and enable</x-ui.button>
                    </x-form.actions>
                </x-form.form>
            @else
                <p class="mb-4 text-sm text-ink-soft">Two-factor authentication is active. Keep your recovery codes somewhere safe: each one signs you in once if you lose your phone.</p>

                @if (count($twoFactor['recoveryCodes']))
                    <x-ui.alert tone="warning" title="Save these recovery codes now" class="mb-4">
                        They are shown only once. Store them in a password manager or print them.
                    </x-ui.alert>
                    <ul class="mb-5 grid grid-cols-1 gap-2 rounded-2xl border border-line bg-surface-muted p-4 font-mono text-[13px] sm:grid-cols-2">
                        @foreach ($twoFactor['recoveryCodes'] as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="flex flex-wrap gap-3">
                    <x-form.form action="/user/two-factor-recovery-codes">
                        <x-ui.button type="submit" variant="secondary" icon="arrow-path">Generate new recovery codes</x-ui.button>
                    </x-form.form>

                    <x-ui.confirm action="/user/two-factor-authentication" method="DELETE"
                                  title="Turn off two-factor authentication?"
                                  message="Your account will be protected by your password alone."
                                  confirm="Turn off" tone="danger">
                        <x-ui.button variant="ghost" icon="x-circle">Turn off</x-ui.button>
                    </x-ui.confirm>
                </div>
            @endif
        </x-ui.card>

        {{-- Password --}}
        <x-ui.card title="Change password" description="Other devices are signed out when you change it.">
            <x-form.form :action="route('account.password.update')" method="PUT" class="space-y-5">
                <x-form.password name="current_password" label="Current password" required autocomplete="current-password" />
                <x-form.password name="password" label="New password" required autocomplete="new-password" hint="At least 8 characters. A longer phrase is stronger." />
                <x-form.password name="password_confirmation" label="Confirm new password" required autocomplete="new-password" />

                <x-form.actions>
                    <x-ui.button type="submit">Update password</x-ui.button>
                </x-form.actions>
            </x-form.form>
        </x-ui.card>
    </div>
</x-account.layout>
