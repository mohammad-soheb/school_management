<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\SaveHolidayRequest;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Services\SchoolSetup\AcademicYearService;
use App\Services\SchoolSetup\HolidayService;
use App\Services\SchoolSetup\SchoolCalendar;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The school calendar: holidays, events and exam periods, listed month by month (FR-202). */
class HolidayController extends Controller
{
    public function __construct(private readonly HolidayService $holidays) {}

    public function index(Request $request, AcademicYearService $years, SchoolCalendar $calendar): View
    {
        $year = $this->holidays->resolveYear($request->integer('year') ?: null);
        $type = HolidayType::tryFrom($request->string('type')->toString());

        return view('school.setup.holidays.index', [
            'year' => $year,
            'years' => $years->all(),
            'type' => $type,
            'months' => $year === null ? [] : $this->holidays->byMonth($year, $type),
            'workingDays' => $year === null ? null : $calendar->workingDaysBetween($year->starts_on, $year->ends_on),
            'weeklyOff' => $calendar->weeklyOffDays(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SchoolHoliday::class);

        return view('school.setup.holidays.form', ['holiday' => null, 'date' => $request->string('date')->toString() ?: null]);
    }

    public function store(SaveHolidayRequest $request): RedirectResponse
    {
        $holiday = $this->holidays->create($request->validated(), $request->user());

        return redirect()->route('school.setup.holidays.index', ['year' => $holiday->academic_year_id])->with('status', "{$holiday->title} added to the calendar.");
    }

    public function edit(SchoolHoliday $holiday): View
    {
        $this->authorize('update', $holiday);

        return view('school.setup.holidays.form', ['holiday' => $holiday, 'date' => null]);
    }

    public function update(SaveHolidayRequest $request, SchoolHoliday $holiday): RedirectResponse
    {
        $this->holidays->update($holiday, $request->validated(), $request->user());

        return redirect()->route('school.setup.holidays.index', ['year' => $holiday->academic_year_id])->with('status', "{$holiday->title} saved.");
    }

    public function destroy(Request $request, SchoolHoliday $holiday): RedirectResponse
    {
        $this->authorize('delete', $holiday);
        $this->holidays->delete($holiday, $request->user());

        return redirect()->route('school.setup.holidays.index', ['year' => $holiday->academic_year_id])->with('status', "{$holiday->title} removed from the calendar.");
    }
}
