<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\SwitchSchoolRequest;
use App\Services\Identity\SchoolContextService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Lets a person who belongs to several schools pick (or change) the school they are working in. */
class SchoolSwitchController extends Controller
{
    public function __construct(private readonly SchoolContextService $contexts) {}

    public function index(Request $request): View
    {
        return view('identity.select-school', [
            'memberships' => $this->contexts->membershipsFor($request->user()),
            'currentSchoolId' => $request->session()->get('school_id'),
        ]);
    }

    public function store(SwitchSchoolRequest $request): RedirectResponse
    {
        $this->contexts->switchTo($request->user(), (int) $request->validated('school_id'), $request->session());

        return redirect()->route('home');
    }
}
