<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\SaveClassRequest;
use App\Http\Requests\SchoolSetup\SyncClassSubjectsRequest;
use App\Models\SchoolSetup\SchoolClass;
use App\Services\SchoolSetup\ClassStructureService;
use App\Services\SchoolSetup\SubjectService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Classes (grades), their promotion order, sections and subject list (FR-202). */
class ClassController extends Controller
{
    public function __construct(private readonly ClassStructureService $structure) {}

    public function index(): View
    {
        return view('school.setup.classes.index', ['classes' => $this->structure->classes()]);
    }

    public function create(): View
    {
        $this->authorize('create', SchoolClass::class);

        return view('school.setup.classes.form', [
            'class' => null,
            'classOptions' => $this->structure->classOptions(),
        ]);
    }

    public function store(SaveClassRequest $request): RedirectResponse
    {
        $class = $this->structure->createClass($request->validated(), $request->user());

        return redirect()->route('school.setup.classes.show', $class)->with('status', "{$class->name} created. Add its sections and subjects next.");
    }

    public function show(SchoolClass $class, SubjectService $subjects): View
    {
        $this->authorize('view', $class);

        return view('school.setup.classes.show', [
            'class' => $this->structure->loadForShow($class),
            'studentCounts' => $this->structure->sectionStudentCounts($class),
            'teacherOptions' => $this->structure->teacherOptions(),
            'subjects' => $subjects->active(),
        ]);
    }

    public function edit(SchoolClass $class): View
    {
        $this->authorize('update', $class);

        return view('school.setup.classes.form', [
            'class' => $class,
            'classOptions' => $this->structure->classOptions(except: $class->getKey()),
        ]);
    }

    public function update(SaveClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $this->structure->updateClass($class, $request->validated(), $request->user());

        return redirect()->route('school.setup.classes.show', $class)->with('status', "{$class->name} saved.");
    }

    public function destroy(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);
        $this->structure->deleteClass($class, $request->user());

        return redirect()->route('school.setup.classes.index')->with('status', "{$class->name} deleted.");
    }

    public function activate(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $this->structure->setClassActive($class, true, $request->user());

        return back()->with('status', "{$class->name} is active again.");
    }

    public function deactivate(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $this->structure->setClassActive($class, false, $request->user());

        return back()->with('status', "{$class->name} deactivated. Its history is kept.");
    }

    public function syncSubjects(SyncClassSubjectsRequest $request, SchoolClass $class): RedirectResponse
    {
        $this->structure->syncSubjects($class, $request->items(), $request->user());

        return redirect()->route('school.setup.classes.show', $class)->with('status', "Subjects for {$class->name} saved.");
    }
}
