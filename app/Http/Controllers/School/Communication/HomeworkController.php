<?php

namespace App\Http\Controllers\School\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\HomeworkIndexRequest;
use App\Http\Requests\Communication\HomeworkRequest;
use App\Models\Communication\Homework;
use App\Models\Documents\StoredFile;
use App\Services\Communication\HomeworkService;
use App\Services\Documents\PrivateFileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Homework / assignments (FR-505, FR-507, FR-508). */
class HomeworkController extends Controller
{
    public function __construct(
        private readonly HomeworkService $homework,
        private readonly PrivateFileService $files,
    ) {}

    public function index(HomeworkIndexRequest $request): View
    {
        $filters = $request->filters();

        return view('school.homework.index', [
            'homework' => $this->homework->paginate($filters, $request->user()),
            'filters' => $filters,
            'options' => $this->homework->filterOptions($filters['class_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Homework::class);

        return view('school.homework.create', $this->homework->formData($request->user()));
    }

    public function store(HomeworkRequest $request): RedirectResponse
    {
        $homework = $this->homework->create($request->safe()->except(['attachments', 'remove_attachments']), $request->file('attachments', []), $request->user());

        return redirect()->route('school.homework.show', $homework)->with('status', 'Homework set.');
    }

    public function show(Homework $homework): View
    {
        $this->authorize('view', $homework);

        return view('school.homework.show', [
            'homework' => $homework->load(['academicYear', 'schoolClass', 'section', 'subject', 'creator', 'updater', 'attachments']),
        ]);
    }

    public function edit(Request $request, Homework $homework): View
    {
        $this->authorize('update', $homework);

        return view('school.homework.edit', ['homework' => $homework->load('attachments')] + $this->homework->formData($request->user(), $homework));
    }

    public function update(HomeworkRequest $request, Homework $homework): RedirectResponse
    {
        $this->homework->update(
            $homework,
            $request->safe()->except(['attachments', 'remove_attachments']),
            $request->file('attachments', []),
            $request->validated('remove_attachments', []),
            $request->user(),
        );

        return redirect()->route('school.homework.show', $homework)->with('status', 'Homework updated.');
    }

    public function archive(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('archive', $homework);
        $this->homework->archive($homework, $request->user());

        return redirect()->route('school.homework.index')->with('status', 'Homework archived. It is hidden from lists and parents; you can restore it from the archived list.');
    }

    public function restore(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('restore', $homework);
        $this->homework->restore($homework, $request->user());

        return redirect()->route('school.homework.show', $homework)->with('status', 'Homework restored.');
    }

    public function attachment(Request $request, Homework $homework, StoredFile $file): StreamedResponse
    {
        $this->authorize('view', $homework);

        return $this->files->stream($this->homework->attachment($homework, $file), inline: $request->boolean('inline'), actor: $request->user());
    }
}
