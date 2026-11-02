<?php

namespace App\Models\Documents;

use App\Models\User;
use App\Policies\Documents\StoredFilePolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Number;

/**
 * Metadata for a file kept on the private disk. The bytes are only ever served through an authorised route,
 * never through a public URL (FR-109).
 */
#[Fillable(['uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'extension', 'size', 'checksum', 'purpose', 'attachable_type', 'attachable_id'])]
#[UsePolicy(StoredFilePolicy::class)]
class StoredFile extends Model
{
    use BelongsToSchool;

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }
}
