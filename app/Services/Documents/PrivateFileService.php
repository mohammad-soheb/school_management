<?php

namespace App\Services\Documents;

use App\Models\Documents\StoredFile;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * All uploads go through here (FR-109, master plan §19.2): extension allow-list per purpose, size limit, the file's
 * real MIME type checked from its bytes, a random storage key under the school's prefix, SHA-256 checksum, and
 * downloads only through an authorised controller (streamed; there is no public URL).
 */
class PrivateFileService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  string  $purpose  a key of config('documents.purposes')
     */
    public function store(UploadedFile $file, string $purpose, ?Model $attachable = null, ?User $actor = null): StoredFile
    {
        $rules = config("documents.purposes.{$purpose}")
            ?? throw new \InvalidArgumentException("Unknown document purpose [{$purpose}].");

        $schoolId = $this->context->requireId();

        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The upload failed. Please try again.']);
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        if (! in_array($extension, $rules['extensions'], true)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed. Allowed: '.implode(', ', $rules['extensions']).'.']);
        }

        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $rules['max_kb']) {
            throw ValidationException::withMessages(['file' => 'The file is too large (maximum '.round($rules['max_kb'] / 1024, 1).' MB).']);
        }

        // Trust the bytes, not the browser: a renamed executable must not pass as a PDF.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream';
        if (! in_array('*', $rules['mimes'], true) && ! in_array($mime, $rules['mimes'], true)) {
            throw ValidationException::withMessages(['file' => 'The file content does not match an allowed type.']);
        }

        $path = sprintf('schools/%d/%s/%s/%s.%s', $schoolId, $purpose, now()->format('Y/m'), Str::random(40), $extension);
        Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));

        $stored = StoredFile::create([
            'uploaded_by' => ($actor ?? auth()->user())?->getKey(),
            'disk' => 'private',
            'path' => $path,
            'original_name' => Str::limit(preg_replace('/[^\p{L}\p{N}\s._\-()]/u', '', $file->getClientOriginalName()) ?: "file.{$extension}", 200, ''),
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'purpose' => $purpose,
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
        ]);

        $this->audit->record('file.uploaded', $stored, new: ['purpose' => $purpose, 'name' => $stored->original_name, 'size' => $stored->size], actor: $actor);

        return $stored;
    }

    /** Store bytes the system generated (an export, a bulk PDF) as a private file. */
    public function storeContents(string $contents, string $originalName, string $purpose, ?User $actor = null): StoredFile
    {
        $schoolId = $this->context->requireId();
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = sprintf('schools/%d/%s/%s/%s.%s', $schoolId, $purpose, now()->format('Y/m'), Str::random(40), $extension);

        Storage::disk('private')->put($path, $contents);

        return StoredFile::create([
            'uploaded_by' => ($actor ?? auth()->user())?->getKey(),
            'disk' => 'private',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => Storage::disk('private')->mimeType($path) ?: 'application/octet-stream',
            'extension' => $extension,
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'purpose' => $purpose,
        ]);
    }

    /**
     * Stream a file to the browser. Callers must already have authorised the user (StoredFilePolicy::view).
     * Only images and PDFs may display inline; everything is served with nosniff and a sandboxing CSP.
     */
    public function stream(StoredFile $file, bool $inline = false, ?User $actor = null): StreamedResponse
    {
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        $inline = $inline && in_array($file->mime_type, config('documents.inline_mimes', []), true);

        $this->audit->record('file.downloaded', $file, meta: ['purpose' => $file->purpose], actor: $actor);

        return $disk->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function delete(StoredFile $file, ?User $actor = null): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        $this->audit->record('file.deleted', $file, old: ['purpose' => $file->purpose, 'name' => $file->original_name], actor: $actor);
    }
}
