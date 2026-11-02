<?php

namespace App\Http\Controllers\School\Students;

use App\Enums\Students\GuardianRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\InviteGuardianRequest;
use App\Http\Requests\Students\UpdateGuardianRequest;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Services\Identity\InvitationService;
use App\Services\Students\GuardianService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Guardian directory, profile, edit and parent-portal invitation. */
class GuardianController extends Controller
{
    public function __construct(private readonly GuardianService $guardians) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'portal']);

        return view('school.guardians.index', [
            'guardians' => $this->guardians->paginate($filters),
            'filters' => $filters,
        ]);
    }

    /** JSON for <x-form.lookup> on the admission and link forms: existing guardians by name or phone (siblings). */
    public function lookup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('create', Student::class) || $user->can('students.guardians.manage'), 403);

        return response()->json($this->guardians->lookup(mb_substr($request->string('q')->toString(), 0, 60)));
    }

    public function show(Guardian $guardian): View
    {
        $this->authorize('view', $guardian);

        return view('school.guardians.show', [
            'guardian' => $this->guardians->profile($guardian),
            'invitations' => $this->guardians->invitations($guardian),
            'validDays' => InvitationService::VALID_DAYS,
        ]);
    }

    public function edit(Guardian $guardian): View
    {
        $this->authorize('update', $guardian);

        return view('school.guardians.edit', [
            'guardian' => $guardian,
            'relations' => GuardianRelation::options(),
        ]);
    }

    public function update(UpdateGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $this->guardians->update($guardian, $request->validated(), $request->user());

        return redirect()->route('school.guardians.show', $guardian)->with('status', 'Guardian details saved.');
    }

    public function invite(InviteGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $result = $this->guardians->invite($guardian, $request->validated('email'), $request->user());

        return redirect()->route('school.guardians.show', $guardian)
            ->with('status', 'Parent portal invitation sent to '.$result['invitation']->email.'.')
            ->with('invitation_url', $result['url']);
    }
}
