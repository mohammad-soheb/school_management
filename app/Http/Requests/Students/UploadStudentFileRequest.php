<?php

namespace App\Http\Requests\Students;

use App\Models\Students\Student;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A student photo (route school.students.photo) or document (school.students.documents.store). The size cap here is a
 * first line; PrivateFileService applies the per-purpose extension, size and MIME allow-list (config/documents.php).
 */
class UploadStudentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        if (! $student instanceof Student) {
            return false;
        }

        return $this->isPhoto()
            ? $this->user()->can('update', $student)
            : $this->user()->can('uploadDocuments', $student);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $purpose = $this->isPhoto() ? 'student_photo' : 'student_document';
        $config = config("documents.purposes.{$purpose}");

        return [
            'file' => ['required', 'file', 'max:'.$config['max_kb'], 'extensions:'.implode(',', $config['extensions'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file.extensions' => 'This file type is not allowed.'];
    }

    private function isPhoto(): bool
    {
        return $this->routeIs('school.students.photo');
    }
}
