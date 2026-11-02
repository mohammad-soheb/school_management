<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Enums\SchoolSetup\SubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\SaveSubjectRequest;
use App\Models\SchoolSetup\Subject;
use App\Services\SchoolSetup\SubjectService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Subjects of the school (FR-202). */
class SubjectController extends Controller
{
    public function __construct(private readonly SubjectService $subjects) {}

    public function index(Request $request): View
    {
        return view('school.setup.subjects.index', [
            'subjects' => $this->subjects->paginate($request->only(['q', 'type', 'status'])),
            'types' => collect(SubjectType::cases())->mapWithKeys(fn (SubjectType $t) => [$t->value => $t->label()])->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Subject::class);

        return view('school.setup.subjects.form', ['subject' => null]);
    }

    public function store(SaveSubjectRequest $request): RedirectResponse
    {
        $subject = $this->subjects->create($request->validated(), $request->user());

        return redirect()->route('school.setup.subjects.index')->with('status', "{$subject->name} added.");
    }

    public function edit(Subject $subject): View
    {
        $this->authorize('update', $subject);

        return view('school.setup.subjects.form', ['subject' => $subject]);
    }

    public function update(SaveSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $this->subjects->update($subject, $request->validated(), $request->user());

        return redirect()->route('school.setup.subjects.index')->with('status', "{$subject->name} saved.");
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('delete', $subject);
        $this->subjects->delete($subject, $request->user());

        return redirect()->route('school.setup.subjects.index')->with('status', "{$subject->name} deleted.");
    }

    public function activate(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('update', $subject);
        $this->subjects->setActive($subject, true, $request->user());

        return back()->with('status', "{$subject->name} is active again.");
    }

    public function deactivate(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('update', $subject);
        $this->subjects->setActive($subject, false, $request->user());

        return back()->with('status', "{$subject->name} deactivated. Its history is kept.");
    }
}
