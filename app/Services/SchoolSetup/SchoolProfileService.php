<?php

namespace App\Services\SchoolSetup;

use App\Models\Documents\StoredFile;
use App\Models\Platform\School;
use App\Models\User;
use App\Services\Documents\PrivateFileService;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The active school's profile (FR-201): name, board, contact details, address and logo.
 *
 * The school code and status belong to the platform backoffice and are never changed here. The logo is a private
 * file (purpose "school_logo") served through the authorised file route; `schools.logo_file_id` points at it.
 */
class SchoolProfileService
{
    /** Profile columns a school may edit itself. */
    public const FIELDS = ['name', 'board', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'pincode'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly PrivateFileService $files,
    ) {}

    public function school(): School
    {
        return $this->context->requireSchool();
    }

    /** The current logo file, or null. */
    public function logo(): ?StoredFile
    {
        $id = $this->school()->logo_file_id;

        return $id === null ? null : StoredFile::query()->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>  $data  validated profile fields
     */
    public function update(array $data, ?UploadedFile $logo, bool $removeLogo, User $actor): School
    {
        $school = $this->school();

        // Store the upload first (outside the transaction): a rejected file must not leave half-saved profile data.
        $newLogo = $logo !== null ? $this->files->store($logo, 'school_logo', actor: $actor) : null;
        $oldLogoId = null;

        DB::transaction(function () use ($school, $data, $newLogo, $removeLogo, $actor, &$oldLogoId): void {
            School::query()->whereKey($school->getKey())->lockForUpdate()->first(['id']);
            $school->refresh();

            $before = $this->snapshot($school);
            $attributes = [];
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                    $attributes[$field] = $value === '' ? null : $value;
                }
            }

            $school->fill($attributes);

            if ($newLogo !== null || $removeLogo) {
                $oldLogoId = $school->logo_file_id;
                // logo_file_id is not mass assignable on the (platform) School model.
                $school->forceFill(['logo_file_id' => $newLogo?->getKey()]);
            }

            $school->save();

            [$old, $new] = $this->audit->diff($before, $this->snapshot($school));
            if ($new !== []) {
                $this->audit->record('school.profile_updated', $school, $old, $new, actor: $actor);
            }
        });

        // Remove the replaced logo's bytes; nothing else references a school logo.
        if ($oldLogoId !== null && $oldLogoId !== $newLogo?->getKey()) {
            $old = StoredFile::query()->whereKey($oldLogoId)->where('purpose', 'school_logo')->first();
            if ($old !== null) {
                $this->files->delete($old, $actor);
            }
        }

        return $school;
    }

    /** @return array<string, mixed> */
    private function snapshot(School $school): array
    {
        $snapshot = [];
        foreach (self::FIELDS as $field) {
            $snapshot[$field] = $school->getAttribute($field);
        }
        $snapshot['logo_file_id'] = $school->logo_file_id;

        return $snapshot;
    }
}
