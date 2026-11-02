<?php

namespace App\Http\Controllers\School\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\BulkPromotionItemsRequest;
use App\Http\Requests\Promotion\CommitPromotionRunRequest;
use App\Http\Requests\Promotion\StorePromotionRunRequest;
use App\Http\Requests\Promotion\UpdatePromotionItemsRequest;
use App\Models\Promotion\PromotionRun;
use App\Services\Promotion\PromotionQueryService;
use App\Services\Promotion\PromotionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Year-end promotion runs: list, wizard, preview/edit, confirm and commit (FR-2B02..05). */
class PromotionRunController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotion,
        private readonly PromotionQueryService $query,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', PromotionRun::class);

        return view('school.promotion.index', [
            'runs' => $this->query->paginate(),
            'years' => $this->query->years(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PromotionRun::class);

        return view('school.promotion.create', $this->query->createOptions($request->integer('from') ?: null));
    }

    public function store(StorePromotionRunRequest $request): RedirectResponse
    {
        $run = $this->promotion->createDraft($request->payload(), $request->user());

        return redirect()->route('school.promotion.show', $run)
            ->with('status', "Draft run #{$run->getKey()} prepared. Review each class, then confirm.");
    }

    public function show(Request $request, PromotionRun $run): View
    {
        $this->authorize('view', $run);
        $run->load(['fromYear', 'toYear', 'creator:id,name', 'committer:id,name']);

        $overview = $this->query->overview($run);
        $classIds = $overview['classes']->map(fn (array $c) => $c['class']->getKey());
        $classId = $classIds->contains($request->integer('class')) ? $request->integer('class') : $classIds->first();

        return view('school.promotion.show', [
            'run' => $run,
            'classId' => $classId,
            ...$overview,
            ...($classId ? $this->query->classItems($run, $classId) : ['items' => collect(), 'sectionOptions' => [], 'flags' => []]),
        ]);
    }

    public function updateItems(UpdatePromotionItemsRequest $request, PromotionRun $run): RedirectResponse
    {
        $changed = $this->promotion->updateItems($run, $request->validated('items'), $request->user());

        return back()->with('status', $changed === 0 ? 'No changes to save.' : "Saved changes for {$changed} student(s).");
    }

    public function bulk(BulkPromotionItemsRequest $request, PromotionRun $run): RedirectResponse
    {
        $changed = $this->promotion->bulk($run, $request->validated('action'), $request->validated('item_ids'), $request->user());

        return back()->with('status', "Updated {$changed} student(s).");
    }

    public function confirm(PromotionRun $run): View|RedirectResponse
    {
        $this->authorize('commit', $run);

        if (! $run->isDraft()) {
            return redirect()->route('school.promotion.show', $run);
        }

        $run->load(['fromYear', 'toYear']);

        return view('school.promotion.confirm', ['run' => $run, ...$this->query->overview($run)]);
    }

    public function commit(CommitPromotionRunRequest $request, PromotionRun $run): RedirectResponse
    {
        $result = $this->promotion->commit($run, $request->user());

        $redirect = redirect()->route('school.promotion.show', $result->run);

        if ($result->alreadyCommitted) {
            return $redirect->with('info', 'This run was already committed. Nothing was changed.');
        }

        $redirect->with('status', sprintf(
            'Run committed: %d promoted, %d retained, %d graduated, %d transferred, %d withdrawn, %d skipped.',
            $result->count('promoted'), $result->count('retained'), $result->count('graduated'),
            $result->count('transferred'), $result->count('withdrawn'), $result->count('skipped'),
        ));

        if ($result->run->option('lock_source_year') && $result->count('source_locked') === 0) {
            $redirect->with('warning', 'The source year is still the current year, so it was not locked. Make the new year current in school setup, then lock it from this page.');
        }

        return $redirect;
    }

    public function cancel(Request $request, PromotionRun $run): RedirectResponse
    {
        $this->authorize('update', $run);
        $this->promotion->cancel($run, $request->user());

        return redirect()->route('school.promotion.index')->with('status', "Draft run #{$run->getKey()} cancelled.");
    }

    public function lockSource(Request $request, PromotionRun $run): RedirectResponse
    {
        $this->authorize('update', $run);

        return $this->promotion->lockSourceYear($run, $request->user())
            ? back()->with('status', 'Source year locked.')
            : back()->with('warning', 'The source year is still the current year. Make the new year current in school setup first.');
    }
}
