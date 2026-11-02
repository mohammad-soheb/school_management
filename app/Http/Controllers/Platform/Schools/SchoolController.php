<?php

namespace App\Http\Controllers\Platform\Schools;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Http\Requests\Platform\SuspendSchoolRequest;
use App\Http\Requests\Platform\UpdateSchoolRequest;
use App\Models\Platform\School;
use App\Services\Platform\SchoolRegistryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Super Admin backoffice: the registry of customer schools (FR-801, FR-802). */
class SchoolController extends Controller
{
    public function __construct(private readonly SchoolRegistryService $registry) {}

    public function index(Request $request): View
    {
        return view('platform.schools.index', [
            'schools' => $this->registry->paginate($request->string('q')->toString(), $request->string('status')->toString()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('platform.schools.manage');

        return view('platform.schools.create');
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $result = $this->registry->create($request->validated(), $request->user());

        // The one-time invitation link is shown once so it can be shared even if email is not configured.
        return redirect()->route('platform.schools.show', $result['school'])
            ->with('status', 'School created. The first School Admin has been invited.')
            ->with('invitation_url', $result['invitation_url']);
    }

    public function show(School $school): View
    {
        return view('platform.schools.show', ['school' => $school]);
    }

    public function edit(School $school): View
    {
        $this->authorize('platform.schools.manage');

        return view('platform.schools.edit', ['school' => $school]);
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $this->registry->update($school, $request->validated(), $request->user());

        return redirect()->route('platform.schools.show', $school)->with('status', 'School updated.');
    }

    public function suspend(SuspendSchoolRequest $request, School $school): RedirectResponse
    {
        $this->registry->suspend($school, $request->validated('reason'), $request->user());

        return back()->with('status', 'School suspended. Its users can no longer sign in to it.');
    }

    public function activate(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.schools.manage');
        $this->registry->activate($school, $request->user());

        return back()->with('status', 'School activated.');
    }
}
