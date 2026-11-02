<?php

namespace App\Services\Students;

use App\Models\Documents\StoredFile;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Documents\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Student documents (FR-206): stored privately through PrivateFileService (allow-list, size, MIME sniffing, random
 * keys) with purpose "student_document" and the student as attachable. Downloads go through school.files.show, where
 * StoredFilePolicy delegates to StudentPolicy::view.
 */
class StudentDocumentService
{
    public const PURPOSE = 'student_document';

    /** A student's file cabinet is small; this cap stops a runaway uploader filling the disk. */
    public const MAX_PER_STUDENT = 50;

    public function __construct(private readonly PrivateFileService $files) {}

    /** @return Collection<int, StoredFile> */
    public function list(Student $student): Collection
    {
        return StoredFile::query()
            ->where('purpose', self::PURPOSE)
            ->where('attachable_type', $student->getMorphClass())
            ->where('attachable_id', $student->getKey())
            ->with('uploader')
            ->latest('id')
            ->get();
    }

    public function upload(Student $student, UploadedFile $file, User $actor): StoredFile
    {
        if ($this->list($student)->count() >= self::MAX_PER_STUDENT) {
            throw ValidationException::withMessages(['file' => 'This student already has '.self::MAX_PER_STUDENT.' documents. Remove an old one first.']);
        }

        return $this->files->store($file, self::PURPOSE, $student, $actor);
    }

    /** Remove a document. The file belongs to this student (enforced by the scoped route binding and checked again here). */
    public function delete(Student $student, StoredFile $file, User $actor): void
    {
        if ($file->purpose !== self::PURPOSE || $file->attachable_type !== $student->getMorphClass() || (int) $file->attachable_id !== (int) $student->getKey()) {
            abort(404);
        }

        $this->files->delete($file, $actor);
    }
}
