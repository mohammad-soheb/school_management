<?php

namespace App\Http\Controllers\School\Students;

use App\Enums\Students\StudentStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\UploadStudentFileRequest;
use App\Models\Documents\StoredFile;
use App\Models\Students\Student;
use App\Services\Students\StudentDocumentService;
use App\Services\Students\StudentQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Student documents tab (FR-206). Files are downloaded through school.files.show (StoredFilePolicy). */
class StudentDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documents,
        private readonly StudentQueryService $query,
    ) {}

    public function index(Student $student): View
    {
        $this->authorize('viewDocuments', $student);

        return view('school.students.documents', [
            'student' => $this->query->profile($student),
            'statusActions' => StudentStatusAction::availableFor($student->status),
            'documents' => $this->documents->list($student),
            'rules' => config('documents.purposes.'.StudentDocumentService::PURPOSE),
            'tab' => 'documents',
        ]);
    }

    public function store(UploadStudentFileRequest $request, Student $student): RedirectResponse
    {
        $file = $this->documents->upload($student, $request->file('file'), $request->user());

        return redirect()->route('school.students.documents.index', $student)->with('status', "\"{$file->original_name}\" uploaded.");
    }

    public function destroy(Request $request, Student $student, StoredFile $document): RedirectResponse
    {
        $this->authorize('uploadDocuments', $student);
        $this->documents->delete($student, $document, $request->user());

        return redirect()->route('school.students.documents.index', $student)->with('status', 'Document removed.');
    }
}
