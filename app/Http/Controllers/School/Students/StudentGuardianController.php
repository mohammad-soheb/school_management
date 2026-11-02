<?php

namespace App\Http\Controllers\School\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\LinkGuardianRequest;
use App\Http\Requests\Students\UpdateGuardianLinkRequest;
use App\Models\Students\Student;
use App\Models\Students\StudentGuardian;
use App\Services\Students\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Guardian links on a student's profile: link, edit flags, verify, revoke (FR-204, FR-205, FR-602). */
class StudentGuardianController extends Controller
{
    public function __construct(private readonly GuardianService $guardians) {}

    public function store(LinkGuardianRequest $request, Student $student): RedirectResponse
    {
        $link = $this->guardians->link($student, $request->validated(), $request->user());

        return redirect()->route('school.students.guardians', $student)->with('status', "{$link->guardian->name} linked.");
    }

    public function update(UpdateGuardianLinkRequest $request, Student $student, StudentGuardian $guardianLink): RedirectResponse
    {
        $this->guardians->updateLink($guardianLink, $request->validated(), $request->user());

        return redirect()->route('school.students.guardians', $student)->with('status', 'Guardian link updated.');
    }

    public function verify(Request $request, Student $student, StudentGuardian $guardianLink): RedirectResponse
    {
        $this->authorize('verify', $guardianLink);
        $this->guardians->verify($guardianLink, $request->user());

        return redirect()->route('school.students.guardians', $student)->with('status', 'Relationship verified. The parent portal can now show this child to the guardian.');
    }

    public function revoke(Request $request, Student $student, StudentGuardian $guardianLink): RedirectResponse
    {
        $this->authorize('revoke', $guardianLink);
        $this->guardians->revoke($guardianLink, $request->user());

        return redirect()->route('school.students.guardians', $student)->with('status', 'Link revoked. The guardian no longer has access to this child.');
    }
}
