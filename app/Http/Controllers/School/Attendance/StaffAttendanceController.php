<?php

namespace App\Http\Controllers\School\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceDateRequest;
use App\Http\Requests\Attendance\CorrectStaffAttendanceRequest;
use App\Http\Requests\Attendance\SubmitStaffAttendanceRequest;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\StaffAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Daily staff attendance (FR-302) and its corrections (FR-304, FR-307). */
class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceService $staff,
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    public function index(AttendanceDateRequest $request): View
    {
        $date = $request->date();

        return view('school.attendance.staff', $this->staff->day($date) + [
            'corrections' => $this->corrections->historyForStaffDay($date),
        ]);
    }

    public function store(SubmitStaffAttendanceRequest $request): RedirectResponse
    {
        $result = $this->staff->submit($request->date(), $request->validated('staff'), $request->user());

        return redirect()
            ->route('school.attendance.staff.index', ['date' => $request->date()->toDateString()])
            ->with($result['created'] > 0 ? 'status' : 'warning', $result['created'] > 0
                ? "Staff attendance saved ({$result['created']} marked)."
                : 'Staff attendance for this date was already saved. Nothing was changed; use Correct to change it.');
    }

    public function correct(CorrectStaffAttendanceRequest $request): RedirectResponse
    {
        $changed = $this->corrections->correctStaff($request->date(), $request->validated('records'), $request->validated('reason'), $request->user());

        return redirect()
            ->route('school.attendance.staff.index', ['date' => $request->date()->toDateString()])
            ->with('status', $changed === 1 ? '1 record corrected.' : "{$changed} records corrected.");
    }
}
