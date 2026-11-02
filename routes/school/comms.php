<?php

use App\Http\Controllers\School\Communication\HomeworkController;
use App\Http\Controllers\School\Communication\NoticeController;
use Illuminate\Support\Facades\Route;

// Homework & notices (FR-505 - FR-508). Names are prefixed "school." and URLs "/app".
// Record-level rules (teacher's assigned classes/subjects, notice audience) are enforced by the policies.

Route::prefix('homework')->name('homework.')->middleware('can:homework.access')->group(function () {
    Route::get('/', [HomeworkController::class, 'index'])->name('index');

    Route::middleware('can:homework.author')->group(function () {
        Route::get('create', [HomeworkController::class, 'create'])->name('create');
        Route::post('/', [HomeworkController::class, 'store'])->name('store');
        Route::get('{homework}/edit', [HomeworkController::class, 'edit'])->whereNumber('homework')->name('edit');
        Route::put('{homework}', [HomeworkController::class, 'update'])->whereNumber('homework')->name('update');
        Route::post('{homework}/archive', [HomeworkController::class, 'archive'])->whereNumber('homework')->name('archive');
        Route::post('{homework}/restore', [HomeworkController::class, 'restore'])->whereNumber('homework')->name('restore');
    });

    Route::get('{homework}', [HomeworkController::class, 'show'])->whereNumber('homework')->name('show');
    Route::get('{homework}/attachments/{file}', [HomeworkController::class, 'attachment'])
        ->whereNumber(['homework', 'file'])->middleware('throttle:downloads')->name('attachments.show');
});

Route::prefix('notices')->name('notices.')->middleware('can:notices.access')->group(function () {
    Route::get('/', [NoticeController::class, 'index'])->name('index');

    Route::middleware('can:notices.manage')->group(function () {
        Route::get('manage', [NoticeController::class, 'manage'])->name('manage');
        Route::get('create', [NoticeController::class, 'create'])->name('create');
        Route::post('/', [NoticeController::class, 'store'])->name('store');
        Route::get('{notice}/edit', [NoticeController::class, 'edit'])->whereNumber('notice')->name('edit');
        Route::put('{notice}', [NoticeController::class, 'update'])->whereNumber('notice')->name('update');
        Route::post('{notice}/publish', [NoticeController::class, 'publish'])->whereNumber('notice')->name('publish');
        Route::post('{notice}/unpublish', [NoticeController::class, 'unpublish'])->whereNumber('notice')->name('unpublish');
        Route::post('{notice}/archive', [NoticeController::class, 'archive'])->whereNumber('notice')->name('archive');
        Route::post('{notice}/restore', [NoticeController::class, 'restore'])->whereNumber('notice')->name('restore');
    });

    Route::get('{notice}', [NoticeController::class, 'show'])->whereNumber('notice')->name('show');
    Route::get('{notice}/attachments/{file}', [NoticeController::class, 'attachment'])
        ->whereNumber(['notice', 'file'])->middleware('throttle:downloads')->name('attachments.show');
});
