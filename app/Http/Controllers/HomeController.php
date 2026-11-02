<?php

namespace App\Http\Controllers;

use App\Services\Identity\SchoolContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** The landing route "/": sends each person to the right area after sign-in. */
class HomeController extends Controller
{
    public function __invoke(Request $request, SchoolContextService $contexts): RedirectResponse|View
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        $memberships = $contexts->membershipsFor($user);

        if ($memberships->isEmpty()) {
            return view('account.no-access');
        }

        $selected = $memberships->firstWhere('school_id', $request->session()->get('school_id'))
            ?? ($memberships->count() === 1 ? $memberships->first() : null);

        if ($selected === null) {
            return redirect()->route('school.select');
        }

        $request->session()->put('school_id', $selected->school_id);

        // A login that only holds the Parent role lives in the parent portal; anyone else starts at the school app.
        $staffRoles = $selected->roles->reject(fn ($role) => $role->slug === 'parent');

        return $staffRoles->isEmpty() && $selected->isGuardian()
            ? redirect()->route('parent.dashboard')
            : redirect()->route('school.dashboard');
    }
}
