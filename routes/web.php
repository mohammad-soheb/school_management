<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Identity\InvitationController;
use App\Http\Controllers\Identity\SchoolSwitchController;
use Illuminate\Support\Facades\Route;

/*
| Routes that belong to no area. The three areas (platform, school, parent) are registered in bootstrap/app.php,
| each with its own middleware stack, and load routes/<area>/*.php. Sign-in, password reset and two-factor
| challenge routes are provided by Laravel Fortify.
*/

Route::get('/', HomeController::class)->name('home');

// Accepting an invitation is public and throttled: the secret token in the URL is the credential.
Route::middleware('throttle:invitations')->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'store'])->name('invitations.accept');
});

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/select-school', [SchoolSwitchController::class, 'index'])->name('school.select');
    Route::post('/select-school', [SchoolSwitchController::class, 'store'])->name('school.switch');

    // Account pages use the school shell when the person has a valid school, and a plain shell otherwise
    // (Super Admin, or someone between schools). 'soft' never blocks the request.
    Route::middleware('school.context:soft')->group(function () {
        Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile');
        Route::put('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
        Route::get('/account/security', [SecurityController::class, 'show'])->name('account.security');
        Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
    });
});
