<?php

namespace App\Http\Controllers\School\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\ReopenYearRequest;
use App\Models\SchoolSetup\AcademicYear;
use App\Services\Promotion\AcademicYearLockService;
use Illuminate\Http\RedirectResponse;

/** FR-2B07: reopen a locked academic year for corrections (promotion.reopen, reason audited). */
class YearReopenController extends Controller
{
    public function __invoke(ReopenYearRequest $request, AcademicYear $year, AcademicYearLockService $locks): RedirectResponse
    {
        $year = $locks->reopen($year, $request->validated('reason'), $request->user());

        return back()->with('status', "{$year->name} reopened. Lock it again when the corrections are done.");
    }
}
