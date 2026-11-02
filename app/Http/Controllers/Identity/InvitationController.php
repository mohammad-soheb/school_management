<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AcceptInvitationRequest;
use App\Models\User;
use App\Services\Identity\InvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Public pages for accepting an emailed invitation. Possession of the secret token is the only credential. */
class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->invitations->findPending($token);

        if ($invitation === null) {
            return view('identity.invitation-invalid');
        }

        $existing = User::query()->where('email', Str::lower($invitation->email))->first();

        // Someone who already has a login signs in first, then confirms; they never set a second password here.
        if ($existing !== null && $request->user()?->getKey() !== $existing->getKey()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login')->with('status', 'Sign in as '.$invitation->email.' to accept this invitation.');
        }

        return view('identity.accept-invitation', [
            'invitation' => $invitation,
            'token' => $token,
            'hasAccount' => $existing !== null,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findPending($token);

        if ($invitation === null) {
            return redirect()->route('invitations.show', $token);
        }

        $existing = User::query()->where('email', Str::lower($invitation->email))->first();

        if ($existing !== null && $request->user()?->getKey() !== $existing->getKey()) {
            return redirect()->route('login');
        }

        $user = $this->invitations->accept($invitation, $request->validated(), $existing);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('school_id', $invitation->school_id);

        return redirect()->route('home')->with('status', 'Welcome to '.$invitation->school->name.'.');
    }
}
