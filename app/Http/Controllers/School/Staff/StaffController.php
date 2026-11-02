<?php

namespace App\Http\Controllers\School\Staff;

use App\Enums\Shared\Gender;
use App\Enums\Staff\StaffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangeStaffStatusRequest;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Http\Requests\Staff\LinkStaffUserRequest;
use App\Http\Requests\Staff\StaffProfileRequest;
use App\Http\Requests\Staff\UploadStaffFileRequest;
use App\Models\Staff\StaffProfile;
use App\Services\Identity\InvitationService;
use App\Services\Staff\StaffService;
use App\Services\Staff\TeacherAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Staff profiles (FR-208, FR-209): list, add, profile, edit, status, photo and login link/invite. */
class StaffController extends Controller
{
    public function __construct(
        private readonly StaffService $staff,
        private readonly TeacherAssignmentService $assignments,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'designation', 'sort', 'dir']);

        return view('school.staff.index', [
            'staffMembers' => $this->staff->paginate($filters),
            'statuses' => $this->statusOptions(),
            'designations' => $this->staff->designations(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('school.staff.create', ['genders' => Gender::options()]);
    }

    public function store(StaffProfileRequest $request): RedirectResponse
    {
        $staff = $this->staff->create($request->validated(), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', "{$staff->fullName()} added.");
    }

    public function show(Request $request, StaffProfile $staff): View
    {
        $this->authorize('view', $staff);
        $user = $request->user();

        return view('school.staff.show', [
            'staff' => $staff->load('user'),
            'assignments' => $this->assignments->forStaff($staff),
            'assignmentOptions' => $user->can('manageAssignments', $staff) ? $this->assignments->formOptions() : null,
            'documents' => $this->staff->documents($staff),
            'linkableMembers' => $user->can('update', $staff) && $staff->user_id === null ? $this->staff->linkableMembers() : [],
            'roles' => $user->can('invite', $staff) ? $this->staff->invitableRoles() : [],
            'statuses' => $this->statusOptions(),
            'validDays' => InvitationService::VALID_DAYS,
            'documentRules' => config('documents.purposes.'.StaffService::DOCUMENT_PURPOSE),
        ]);
    }

    public function edit(StaffProfile $staff): View
    {
        $this->authorize('update', $staff);

        return view('school.staff.edit', ['staff' => $staff, 'genders' => Gender::options()]);
    }

    public function update(StaffProfileRequest $request, StaffProfile $staff): RedirectResponse
    {
        $this->staff->update($staff, $request->validated(), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Staff details saved.');
    }

    public function changeStatus(ChangeStaffStatusRequest $request, StaffProfile $staff): RedirectResponse
    {
        $staff = $this->staff->changeStatus($staff, StaffStatus::from($request->validated('status')), $request->validated('left_on'), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Status changed to '.$staff->status->label().'.');
    }

    public function photo(UploadStaffFileRequest $request, StaffProfile $staff): RedirectResponse
    {
        $this->staff->updatePhoto($staff, $request->file('file'), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Photo updated.');
    }

    public function linkUser(LinkStaffUserRequest $request, StaffProfile $staff): RedirectResponse
    {
        $this->staff->linkUser($staff, (int) $request->validated('user_id'), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Login linked to this profile.');
    }

    public function unlinkUser(Request $request, StaffProfile $staff): RedirectResponse
    {
        $this->authorize('update', $staff);
        $this->staff->unlinkUser($staff, $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Login unlinked. Their school access is unchanged (see Users).');
    }

    public function invite(InviteStaffRequest $request, StaffProfile $staff): RedirectResponse
    {
        $result = $this->staff->invite($staff, $request->validated('email'), $request->validated('roles'), $request->user());

        return redirect()->route('school.staff.show', $staff)
            ->with('status', 'Invitation sent to '.$result['invitation']->email.'.')
            ->with('invitation_url', $result['url']);
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return array_column(array_map(fn (StaffStatus $s) => ['v' => $s->value, 'l' => $s->label()], StaffStatus::cases()), 'l', 'v');
    }
}
