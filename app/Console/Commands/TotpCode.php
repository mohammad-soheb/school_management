<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

/** Local development helper: prints the current two-factor code for a user so you can sign in as the Super Admin. */
class TotpCode extends Command
{
    protected $signature = 'erp:totp {email : Email of a user with two-factor authentication set up}';

    protected $description = 'Print the current two-factor (TOTP) code for a user (local environment only)';

    public function handle(Google2FA $google2fa): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command only runs in the local environment.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null || $user->two_factor_secret === null) {
            $this->error('No such user, or the user has not set up two-factor authentication.');

            return self::FAILURE;
        }

        $this->info($google2fa->getCurrentOtp(decrypt($user->two_factor_secret)));

        return self::SUCCESS;
    }
}
