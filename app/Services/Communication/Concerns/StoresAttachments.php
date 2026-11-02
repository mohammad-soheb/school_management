<?php

namespace App\Services\Communication\Concerns;

use App\Models\User;
use App\Services\Documents\PrivateFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Private attachments for homework and notices: count limit, upload through PrivateFileService (type/size/real MIME
 * checks, random private path), and cleanup of already-written bytes when the surrounding transaction fails.
 *
 * @property-read PrivateFileService $files
 */
trait StoresAttachments
{
    public const MAX_ATTACHMENTS = 5;

    protected function assertAttachmentCount(int $kept, int $adding): void
    {
        if ($kept + $adding > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages(['attachments' => 'You can attach at most '.self::MAX_ATTACHMENTS.' files.']);
        }
    }

    /**
     * @param  list<UploadedFile>  $attachments
     * @param  list<string>  $stored  storage paths written so far (for cleanup if the transaction fails)
     */
    protected function storeAttachments(Model $owner, string $purpose, array $attachments, User $actor, array &$stored): void
    {
        foreach ($attachments as $file) {
            try {
                $stored[] = $this->files->store($file, $purpose, $owner, $actor)->path;
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['attachments' => $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first()]);
            }
        }
    }

    /**
     * Run $work in a transaction; if it fails, delete any file bytes it had already written.
     *
     * @template T
     *
     * @param  callable(array<int, string>&): T  $work
     * @return T
     */
    protected function withFileCleanup(callable $work): mixed
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($work, &$stored) {
                return $work($stored);
            });
        } catch (\Throwable $e) {
            if ($stored !== []) {
                Storage::disk('private')->delete($stored);
            }

            throw $e;
        }
    }
}
