<?php

namespace App\Http\Controllers\School\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CorrectAttendanceRequest;
use App\Models\Attendance\AttendanceSheet;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A submitted class register: read-only view and the authorised correction path (FR-304, FR-307). */
class AttendanceSheetController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    public function show(Request $request, AttendanceSheet $sheet): View
    {
        $this->authorize('view', $sheet);

        return view('school.attendance.sheet', $this->attendance->page($request->user(), $sheet->section()->firstOrFail(), $sheet->date));
    }

    public function correct(CorrectAttendanceRequest $request, AttendanceSheet $sheet): RedirectResponse
    {
        $changed = $this->corrections->correctSheet($sheet, $request->validated('records'), $request->validated('reason'), $request->user());

        return redirect()
            ->route('school.attendance.sheets.show', $sheet)
            ->with('status', $changed === 1 ? '1 record corrected.' : "{$changed} records corrected.");
    }
}
