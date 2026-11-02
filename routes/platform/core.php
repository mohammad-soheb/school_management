<?php

use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\Schools\SchoolController;
use Illuminate\Support\Facades\Route;

// Area middleware (bootstrap/app.php): web, auth, active.user, platform.admin, two-factor. Names are prefixed "platform.".

Route::get('/', DashboardController::class)->middleware('can:platform.dashboard')->name('dashboard');

Route::middleware('can:platform.schools.manage')->group(function () {
    Route::resource('schools', SchoolController::class)->except(['destroy']);
    Route::post('schools/{school}/suspend', [SchoolController::class, 'suspend'])->name('schools.suspend');
    Route::post('schools/{school}/activate', [SchoolController::class, 'activate'])->name('schools.activate');
});
