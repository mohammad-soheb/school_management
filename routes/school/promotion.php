<?php

use App\Http\Controllers\School\Promotion\PromotionItemController;
use App\Http\Controllers\School\Promotion\PromotionRunController;
use App\Http\Controllers\School\Promotion\YearReopenController;
use Illuminate\Support\Facades\Route;

// Year-end promotion / rollover (master plan Phase 2B; docs/promotion/ROLLOVER_RULES.md).
// Names: school.promotion.*, URLs: /app/promotion/...

Route::prefix('promotion')->name('promotion.')->group(function () {
    Route::middleware('can:promotion.view')->group(function () {
        Route::get('/', [PromotionRunController::class, 'index'])->name('index');
    });

    Route::middleware('can:promotion.run')->group(function () {
        Route::get('runs/create', [PromotionRunController::class, 'create'])->name('create');
        Route::post('runs', [PromotionRunController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::put('runs/{run}/items', [PromotionRunController::class, 'updateItems'])->name('items.update');
        Route::post('runs/{run}/bulk', [PromotionRunController::class, 'bulk'])->name('items.bulk');
        Route::get('runs/{run}/confirm', [PromotionRunController::class, 'confirm'])->name('confirm');
        Route::post('runs/{run}/commit', [PromotionRunController::class, 'commit'])->name('commit');
        Route::post('runs/{run}/cancel', [PromotionRunController::class, 'cancel'])->name('cancel');
        Route::post('runs/{run}/lock-source', [PromotionRunController::class, 'lockSource'])->name('lock-source');
    });

    Route::get('runs/{run}', [PromotionRunController::class, 'show'])->middleware('can:promotion.view')->name('show');

    Route::middleware('can:promotion.correct')->scopeBindings()->group(function () {
        Route::get('runs/{run}/items/{item}/edit', [PromotionItemController::class, 'edit'])->name('items.edit');
        Route::put('runs/{run}/items/{item}', [PromotionItemController::class, 'update'])->name('items.correct');
        Route::post('runs/{run}/items/{item}/revert', [PromotionItemController::class, 'revert'])->name('items.revert');
    });

    Route::post('years/{year}/reopen', YearReopenController::class)->middleware('can:promotion.reopen')->name('years.reopen');
});
