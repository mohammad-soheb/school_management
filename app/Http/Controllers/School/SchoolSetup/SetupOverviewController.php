<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Services\SchoolSetup\HolidayService;
use App\Services\SchoolSetup\SetupOverviewService;
use Illuminate\Contracts\View\View;

/** School setup landing page: counts per area and the onboarding checklist (FR-201, FR-202). */
class SetupOverviewController extends Controller
{
    public function __invoke(SetupOverviewService $overview, HolidayService $holidays): View
    {
        return view('school.setup.index', [
            'counts' => $overview->counts(),
            'checklist' => $overview->checklist(),
            'upcoming' => $holidays->upcoming(),
        ]);
    }
}
