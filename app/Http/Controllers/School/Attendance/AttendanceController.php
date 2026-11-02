<?php

namespace App\Http\Controllers\School\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceDateRequest;
use App\Http\Requests\Attendance\SubmitAttendanceRequest;
use App\Models\SchoolSetup\Section;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Student attendance for teachers (FR-301, FR-303, FR-304): the "Today" list of the user's classes, the marking
 * grid, and submitting a register.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(AttendanceDateRequest $request): View
    {
        return view('school.attendance.index', $this->attendance->overview($request->user(), $request->date()));
    }

    public function mark(AttendanceDateRequest $request, Section $section): View
    {
        $this->authorize('attendance.view-section', $section);

        return view('school.attendance.sheet', $this->attendance->page($request->user(), $section, $request->date()));
    }

    public function store(SubmitAttendanceRequest $request, Section $section): RedirectResponse
    {
        $result = $this->attendance->submit($section, $request->date(), $request->validated('attendance'), $request->user());

        return redirect()
            ->route('school.attendance.sheets.show', $result['sheet'])
            ->with($result['created'] ? 'status' : 'warning', $result['created']
                ? 'Attendance saved for '.$section->label().'.'
                : 'Attendance for this class and date was already submitted. Nothing was changed; use Correct to change it.');
    }
}
