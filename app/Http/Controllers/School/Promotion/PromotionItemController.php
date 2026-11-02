<?php

namespace App\Http\Controllers\School\Promotion;

use App\Enums\Promotion\PromotionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\CorrectPromotionItemRequest;
use App\Http\Requests\Promotion\RevertPromotionItemRequest;
use App\Models\Promotion\PromotionRun;
use App\Models\Promotion\PromotionRunItem;
use App\Services\Promotion\PromotionCorrectionService;
use App\Services\Promotion\PromotionPlacement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Corrections to one student after a run is committed (FR-2B07). */
class PromotionItemController extends Controller
{
    public function __construct(private readonly PromotionCorrectionService $corrections) {}

    public function edit(PromotionRun $run, PromotionRunItem $item, PromotionPlacement $placement): View
    {
        $this->authorize('correct', $item);

        $item->load(['student', 'fromClass.nextClass', 'enrollment.section', 'targetClass', 'targetSection', 'targetEnrollment']);
        $run->load(['fromYear', 'toYear']);

        $class = $item->fromClass;
        $sections = $placement->sectionsFor(array_filter([$class->next_class_id, $class->getKey()]));
        $sectionOptions = [];
        if ($class->nextClass !== null) {
            $sectionOptions["{$class->nextClass->name} (promote)"] = $sections->get($class->next_class_id, collect())->pluck('name', 'id')->all();
        }
        $sectionOptions["{$class->name} (retain)"] = $sections->get($class->getKey(), collect())->pluck('name', 'id')->all();

        return view('school.promotion.item-edit', [
            'run' => $run,
            'item' => $item,
            'outcomes' => PromotionOutcome::options(),
            'sectionOptions' => $sectionOptions,
        ]);
    }

    public function update(CorrectPromotionItemRequest $request, PromotionRun $run, PromotionRunItem $item): RedirectResponse
    {
        $this->corrections->correct($item, $request->validated(), $request->user());

        return redirect()->route('school.promotion.show', [$run, 'class' => $item->from_class_id])
            ->with('status', 'Correction saved and audited.');
    }

    public function revert(RevertPromotionItemRequest $request, PromotionRun $run, PromotionRunItem $item): RedirectResponse
    {
        $this->corrections->revert($item, $request->validated('reason'), $request->user());

        return redirect()->route('school.promotion.show', [$run, 'class' => $item->from_class_id])
            ->with('status', 'Promotion reverted for this student. Their previous-year enrollment is active again.');
    }
}
