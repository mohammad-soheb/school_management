<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\SaveSectionRequest;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Services\SchoolSetup\ClassStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sections of a class, managed from the class page (FR-202). */
class SectionController extends Controller
{
    public function __construct(private readonly ClassStructureService $structure) {}

    public function store(SaveSectionRequest $request, SchoolClass $class): RedirectResponse
    {
        $section = $this->structure->createSection($class, $request->validated(), $request->user());

        return redirect()->route('school.setup.classes.show', $class)->with('status', "Section {$section->name} added to {$class->name}.");
    }

    public function update(SaveSectionRequest $request, Section $section): RedirectResponse
    {
        $this->structure->updateSection($section, $request->validated(), $request->user());

        return redirect()->route('school.setup.classes.show', $section->school_class_id)->with('status', "Section {$section->name} saved.");
    }

    public function destroy(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('delete', $section);
        $this->structure->deleteSection($section, $request->user());

        return redirect()->route('school.setup.classes.show', $section->school_class_id)->with('status', "Section {$section->name} deleted.");
    }

    public function activate(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('update', $section);
        $this->structure->setSectionActive($section, true, $request->user());

        return redirect()->route('school.setup.classes.show', $section->school_class_id)->with('status', "Section {$section->name} is active again.");
    }

    public function deactivate(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('update', $section);
        $this->structure->setSectionActive($section, false, $request->user());

        return redirect()->route('school.setup.classes.show', $section->school_class_id)->with('status', "Section {$section->name} deactivated. Its history is kept.");
    }
}
