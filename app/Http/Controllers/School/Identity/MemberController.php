<?php

namespace App\Http\Controllers\School\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\InviteMemberRequest;
use App\Http\Requests\Identity\UpdateMemberRolesRequest;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Services\Identity\InvitationService;
use App\Services\Identity\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** School users: who has access, with which roles, and pending invitations. */
class MemberController extends Controller
{
    public function __construct(
        private readonly MembershipService $members,
        private readonly InvitationService $invitations,
    ) {}

    public function index(Request $request): View
    {
        return view('school.access.members', [
            'members' => $this->members->paginate($request->string('q')->toString()),
            'invitations' => $this->invitations->recent(),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        $result = $this->invitations->invite($request->validated('email'), $request->validated('roles'), $request->user());

        return back()
            ->with('status', 'Invitation sent to '.$result['invitation']->email.'.')
            ->with('invitation_url', $result['url']);
    }

    public function updateRoles(UpdateMemberRolesRequest $request, SchoolMembership $membership): RedirectResponse
    {
        $this->members->updateRoles($membership, $request->validated('roles'), $request->user());

        return back()->with('status', 'Roles updated.');
    }

    public function suspend(Request $request, SchoolMembership $membership): RedirectResponse
    {
        $this->authorize('access.members.update');
        $this->members->suspend($membership, $request->user());

        return back()->with('status', 'Access suspended.');
    }

    public function activate(Request $request, SchoolMembership $membership): RedirectResponse
    {
        $this->authorize('access.members.update');
        $this->members->activate($membership, $request->user());

        return back()->with('status', 'Access restored.');
    }

    public function revokeInvitation(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('access.members.invite');
        $this->invitations->revoke($invitation, $request->user());

        return back()->with('status', 'Invitation revoked.');
    }
}
