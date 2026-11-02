<?php

namespace App\Http\Controllers\School\Students;

use App\Enums\Shared\Gender;
use App\Enums\Students\GuardianRelation;
use App\Enums\Students\StudentStatus;
use App\Enums\Students\StudentStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\AdmitStudentRequest;
use App\Http\Requests\Students\ChangeStudentStatusRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Http\Requests\Students\UploadStudentFileRequest;
use App\Models\Students\Student;
use App\Services\Students\StudentQueryService;
use App\Services\Students\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Student register (FR-203, FR-207, FR-210): list, admission, profile, edit, photo and status changes. */
class StudentController extends Controller
{
    public function __construct(
        private readonly StudentQueryService $query,
        private readonly StudentService $students,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'class_id', 'section_id', 'status', 'sort', 'dir']);

        return view('school.students.index', [
            'students' => $this->query->paginate($request->user(), $filters),
            'placement' => $this->query->placementOptions($request->user()),
            'statuses' => array_column(array_map(fn (StudentStatus $s) => ['v' => $s->value, 'l' => $s->label()], StudentStatus::cases()), 'l', 'v'),
            'filters' => $filters,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->query->lookup($request->user(), mb_substr($request->string('q')->toString(), 0, 60)));
    }

    public function create(): View
    {
        return view('school.students.create', $this->formData());
    }

    public function store(AdmitStudentRequest $request): RedirectResponse
    {
        $student = $this->students->admit($request->validated(), $request->user());

        return redirect()->route('school.students.show', $student)->with('status', "{$student->fullName()} admitted (Adm. {$student->admission_no}).");
    }

    public function show(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        return view('school.students.show', [
            'student' => $this->query->profile($student),
            'statusActions' => StudentStatusAction::availableFor($student->status),
            'tab' => 'overview',
        ]);
    }

    public function guardians(Student $student): View
    {
        $this->authorize('view', $student);

        return view('school.students.guardians', [
            'student' => $this->query->profile($student),
            'statusActions' => StudentStatusAction::availableFor($student->status),
            'relations' => GuardianRelation::options(),
            'tab' => 'guardians',
        ]);
    }

    public function history(Student $student): View
    {
        $this->authorize('view', $student);

        return view('school.students.history', [
            'student' => $this->query->profile($student),
            'statusActions' => StudentStatusAction::availableFor($student->status),
            'enrollments' => $this->query->enrollmentHistory($student),
            'changes' => $this->query->statusHistory($student),
            'tab' => 'history',
        ]);
    }

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);

        return view('school.students.edit', [
            ...$this->formData(activeOnly: false),
            'student' => $this->query->profile($student),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update($student, $request->validated(), $request->user());

        return redirect()->route('school.students.show', $student)->with('status', 'Student details saved.');
    }

    public function changeStatus(ChangeStudentStatusRequest $request, Student $student): RedirectResponse
    {
        $change = $this->students->changeStatus($student, $request->action(), $request->validated(), $request->user());

        return redirect()->route('school.students.history', $student)->with('status', 'Status changed to '.$change->to_status->label().'.');
    }

    public function photo(UploadStudentFileRequest $request, Student $student): RedirectResponse
    {
        $this->students->updatePhoto($student, $request->file('file'), $request->user());

        return back()->with('status', 'Photo updated.');
    }

    public function removePhoto(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        $this->students->removePhoto($student, $request->user());

        return back()->with('status', 'Photo removed.');
    }

    /** @return array<string, mixed> */
    private function formData(bool $activeOnly = true): array
    {
        return [
            'placement' => $this->query->placementOptions(activeOnly: $activeOnly),
            'genders' => Gender::options(),
            'relations' => GuardianRelation::options(),
            'bloodGroups' => array_combine(AdmitStudentRequest::BLOOD_GROUPS, AdmitStudentRequest::BLOOD_GROUPS),
            'year' => $this->query->currentYear(),
        ];
    }
}
