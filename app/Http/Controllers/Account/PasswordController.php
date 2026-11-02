<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Services\Identity\AccountService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        // AuthenticateSession stores the new hash for this browser after the request, so this session stays signed
        // in while every other device (holding the old hash) is signed out.
        $this->accounts->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return back()->with('status', 'Your password has been changed. Other devices were signed out.');
    }
}
