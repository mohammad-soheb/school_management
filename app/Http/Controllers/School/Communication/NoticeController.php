<?php

namespace App\Http\Controllers\School\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\NoticeRequest;
use App\Models\Communication\Notice;
use App\Models\Documents\StoredFile;
use App\Services\Communication\NoticeService;
use App\Services\Documents\PrivateFileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Notices / circulars (FR-506, FR-507, FR-508): the staff notice board and, for notices.manage, the editor. */
class NoticeController extends Controller
{
    public function __construct(
        private readonly NoticeService $notices,
        private readonly PrivateFileService $files,
    ) {}

    /** Notice board: live notices addressed to the signed-in staff member. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Notice::class);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        return view('school.notices.board', ['notices' => $this->notices->board($request->user(), $search)]);
    }

    /** Every notice in every state, for publishers. */
    public function manage(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(array_keys(NoticeService::STATES))],
        ]);

        return view('school.notices.manage', ['notices' => $this->notices->paginate($filters), 'filters' => $filters]);
    }

    public function create(): View
    {
        $this->authorize('create', Notice::class);

        return view('school.notices.create', $this->formData(null));
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        $publish = $request->validated('action') === 'publish';
        $notice = $this->notices->create($request->safe()->except(['attachments', 'remove_attachments', 'action']), $request->file('attachments', []), $publish, $request->user());

        return redirect()->route('school.notices.show', $notice)->with('status', $publish ? 'Notice published.' : 'Draft saved.');
    }

    public function show(Notice $notice): View
    {
        $this->authorize('view', $notice);

        return view('school.notices.show', [
            'notice' => $notice->load(['audiences.schoolClass', 'audiences.role', 'attachments', 'creator', 'updater', 'publisher']),
        ]);
    }

    public function edit(Notice $notice): View
    {
        $this->authorize('update', $notice);

        return view('school.notices.edit', $this->formData($notice->load(['audiences', 'attachments'])));
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $this->notices->update(
            $notice,
            $request->safe()->except(['attachments', 'remove_attachments', 'action']),
            $request->file('attachments', []),
            $request->validated('remove_attachments', []),
            $request->user(),
        );

        if ($request->validated('action') === 'publish') {
            $this->notices->publish($notice, $request->user());
        }

        return redirect()->route('school.notices.show', $notice)->with('status', 'Notice updated.');
    }

    public function publish(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('update', $notice);
        $this->notices->publish($notice, $request->user());

        return back()->with('status', 'Notice published.');
    }

    public function unpublish(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('update', $notice);
        $this->notices->unpublish($notice, $request->user());

        return back()->with('status', 'Notice moved back to drafts.');
    }

    public function archive(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('update', $notice);
        $this->notices->archive($notice, $request->user());

        return redirect()->route('school.notices.manage')->with('status', 'Notice archived.');
    }

    public function restore(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('update', $notice);
        $this->notices->restore($notice, $request->user());

        return back()->with('status', 'Notice restored as a draft.');
    }

    public function attachment(Request $request, Notice $notice, StoredFile $file): StreamedResponse
    {
        $this->authorize('view', $notice);

        return $this->files->stream($this->notices->attachment($notice, $file), inline: $request->boolean('inline'), actor: $request->user());
    }

    /** @return array<string, mixed> */
    private function formData(?Notice $notice): array
    {
        return [
            'notice' => $notice,
            'state' => $this->notices->formState($notice),
            'classes' => $this->notices->classOptions(),
            'roles' => $this->notices->roleOptions(),
        ];
    }
}
