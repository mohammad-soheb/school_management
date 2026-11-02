<?php

namespace App\Http\Controllers\School\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\TeacherAssignmentRequest;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use App\Services\Staff\TeacherAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Teacher assignments: the per-section overview, and adding/removing a staff member's classes. */
class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly TeacherAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $options = $this->assignments->formOptions();
        $classId = $request->integer('class_id') ?: null;

        return view('school.staff.assignments', [
            'groups' => $this->assignments->bySection(classId: $classId),
            'classes' => $options['classes'],
            'year' => $this->assignments->currentYear(),
        ]);
    }

    public function store(TeacherAssignmentRequest $request, StaffProfile $staff): RedirectResponse
    {
        $this->assignments->assign($staff, $request->validated(), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Assignment added.');
    }

    public function destroy(Request $request, StaffProfile $staff, TeacherAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);
        $this->assignments->remove($assignment, $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Assignment removed.');
    }
}
