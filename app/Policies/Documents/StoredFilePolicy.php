<?php

namespace App\Policies\Documents;

use App\Models\Documents\StoredFile;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Who may open a stored file. The rule depends on the file's purpose; anything not listed is limited to the user
 * who uploaded it. Documents attached to a record (a student) also require permission to view that record, via the
 * record's own policy - so a teacher cannot read a document of a student outside their classes.
 */
class StoredFilePolicy
{
    public function view(User $user, StoredFile $file): bool
    {
        $schoolId = app(TenantContext::class)->id();

        if ($schoolId === null || (int) $file->school_id !== $schoolId) {
            return false;
        }

        return match ($file->purpose) {
            'school_logo' => true,
            'student_document' => $user->hasPermission('students.documents.view') && $this->canViewAttachable($user, $file),
            'student_photo' => $user->hasPermission('students.view') && $this->canViewAttachable($user, $file),
            'staff_document', 'staff_photo' => $user->hasPermission('staff.view'),
            'homework', 'notice' => $user->hasPermission('homework.view') || $user->hasPermission('notices.view'),
            'import' => $user->hasPermission('imports.run'),
            default => $file->uploaded_by === $user->getKey(),
        };
    }

    public function delete(User $user, StoredFile $file): bool
    {
        if (! $this->view($user, $file)) {
            return false;
        }

        return match ($file->purpose) {
            'student_document', 'student_photo' => $user->hasPermission('students.documents.upload'),
            'staff_document', 'staff_photo' => $user->hasPermission('staff.update'),
            'school_logo' => $user->hasPermission('setup.profile.update'),
            default => $file->uploaded_by === $user->getKey(),
        };
    }

    private function canViewAttachable(User $user, StoredFile $file): bool
    {
        $attachable = $file->attachable;

        return $attachable === null || Gate::forUser($user)->allows('view', $attachable);
    }
}
