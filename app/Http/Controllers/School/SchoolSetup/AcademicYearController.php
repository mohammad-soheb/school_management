<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\LockAcademicYearRequest;
use App\Http\Requests\SchoolSetup\SaveAcademicYearRequest;
use App\Models\SchoolSetup\AcademicYear;
use App\Services\SchoolSetup\AcademicYearService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Academic years: list, create, edit, set current, lock / reopen (FR-202, FR-2B07). */
class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $years) {}

    public function index(): View
    {
        return view('school.setup.years.index', ['years' => $this->years->all()]);
    }

    public function create(): View
    {
        $this->authorize('create', AcademicYear::class);

        return view('school.setup.years.form', ['year' => null, 'suggested' => $this->years->suggestNext()]);
    }

    public function store(SaveAcademicYearRequest $request): RedirectResponse
    {
        $year = $this->years->create($request->validated(), $request->user());

        return redirect()->route('school.setup.years.index')->with('status', "Academic year {$year->name} created.");
    }

    public function edit(AcademicYear $year): View|RedirectResponse
    {
        $this->authorize('update', $year);

        if ($year->isLocked()) {
            return redirect()->route('school.setup.years.index')->with('warning', "{$year->name} is locked. Reopen it to make changes.");
        }

        return view('school.setup.years.form', ['year' => $year, 'suggested' => null]);
    }

    public function update(SaveAcademicYearRequest $request, AcademicYear $year): RedirectResponse
    {
        $this->years->update($year, $request->validated(), $request->user());

        return redirect()->route('school.setup.years.index')->with('status', "Academic year {$year->name} saved.");
    }

    public function setCurrent(Request $request, AcademicYear $year): RedirectResponse
    {
        $this->authorize('setCurrent', $year);
        $this->years->setCurrent($year, $request->user());

        return redirect()->route('school.setup.years.index')->with('status', "{$year->name} is now the current academic year.");
    }

    public function lock(LockAcademicYearRequest $request, AcademicYear $year): RedirectResponse
    {
        $this->years->lock($year, $request->validated('reason'), $request->user());

        return redirect()->route('school.setup.years.index')->with('status', "{$year->name} is locked.");
    }

    public function reopen(LockAcademicYearRequest $request, AcademicYear $year): RedirectResponse
    {
        $this->years->reopen($year, $request->validated('reason'), $request->user());

        return redirect()->route('school.setup.years.index')->with('status', "{$year->name} is open again.");
    }
}
