<?php

use App\Http\Controllers\Platform\Subscriptions\PaymentController;
use App\Http\Controllers\Platform\Subscriptions\PlanController;
use App\Http\Controllers\Platform\Subscriptions\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Platform subscriptions & renewals (FR-803 .. FR-808). Area middleware: web, auth, active.user, platform.admin, two-factor.
// Names are prefixed "platform.", URLs "/platform".

Route::middleware('can:platform.subscriptions.manage')->prefix('subscriptions')->name('subscriptions.')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('index');

    Route::post('plans/hypotheses', [PlanController::class, 'installHypotheses'])->name('plans.hypotheses');
    Route::resource('plans', PlanController::class)->except(['show', 'destroy']);

    Route::prefix('schools/{school}')->name('schools.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show'])->name('show');
        Route::get('assign', [SubscriptionController::class, 'create'])->name('create');
        Route::post('/', [SubscriptionController::class, 'store'])->name('store');
        Route::get('edit', [SubscriptionController::class, 'edit'])->name('edit');
        Route::put('/', [SubscriptionController::class, 'update'])->name('update');
        Route::post('suspend', [SubscriptionController::class, 'suspend'])->name('suspend');
        Route::post('resume', [SubscriptionController::class, 'resume'])->name('resume');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    });
});
