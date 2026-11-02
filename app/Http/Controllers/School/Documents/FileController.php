<?php

namespace App\Http\Controllers\School\Documents;

use App\Http\Controllers\Controller;
use App\Models\Documents\StoredFile;
use App\Services\Documents\PrivateFileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way a stored file leaves the server. Route binding is school-scoped (another school's id is a 404) and
 * StoredFilePolicy decides per purpose whether this user may open it.
 */
class FileController extends Controller
{
    public function __construct(private readonly PrivateFileService $files) {}

    public function show(Request $request, StoredFile $file): StreamedResponse
    {
        $this->authorize('view', $file);

        return $this->files->stream($file, inline: $request->boolean('inline', true), actor: $request->user());
    }
}
