<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the product owner's Super Admin login. Set PLATFORM_ADMIN_EMAIL / PLATFORM_ADMIN_PASSWORD in .env.
 * Two-factor authentication is mandatory for this account: it is forced to set it up on first sign-in. In the local
 * environment, defaults are used and the account starts with a known authenticator secret (see `php artisan erp:totp`).
 */
class PlatformAdminSeeder extends Seeder
{
    public const LOCAL_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public function run(): void
    {
        $local = app()->environment('local');
        $email = env('PLATFORM_ADMIN_EMAIL', $local ? 'admin@erp.test' : null);
        $password = env('PLATFORM_ADMIN_PASSWORD', $local ? 'password' : null);

        if (! $email || ! $password) {
            $this->command?->warn('PLATFORM_ADMIN_EMAIL / PLATFORM_ADMIN_PASSWORD not set: no Super Admin created.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $user->name ?: 'Platform Admin',
            'password' => $user->exists ? $user->password : $password,
            'is_platform_admin' => true,
            'status' => 'active',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if ($local && $user->two_factor_secret === null) {
            $user->forceFill([
                'two_factor_secret' => encrypt(self::LOCAL_TOTP_SECRET),
                'two_factor_recovery_codes' => encrypt(json_encode(['local-recovery-0001', 'local-recovery-0002'])),
                'two_factor_confirmed_at' => now(),
            ]);
        }

        $user->save();
        $this->command?->info("Super Admin ready: {$email}");
    }
}
