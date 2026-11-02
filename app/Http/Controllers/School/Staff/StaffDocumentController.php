<?php

namespace App\Http\Controllers\School\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UploadStaffFileRequest;
use App\Models\Documents\StoredFile;
use App\Models\Staff\StaffProfile;
use App\Services\Staff\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Staff documents (listed on the staff profile; downloaded through school.files.show). */
class StaffDocumentController extends Controller
{
    public function __construct(private readonly StaffService $staff) {}

    public function store(UploadStaffFileRequest $request, StaffProfile $staff): RedirectResponse
    {
        $file = $this->staff->uploadDocument($staff, $request->file('file'), $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', "\"{$file->original_name}\" uploaded.");
    }

    public function destroy(Request $request, StaffProfile $staff, StoredFile $document): RedirectResponse
    {
        $this->authorize('update', $staff);
        $this->staff->deleteDocument($staff, $document, $request->user());

        return redirect()->route('school.staff.show', $staff)->with('status', 'Document removed.');
    }
}
