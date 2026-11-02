<?php

use App\Http\Controllers\School\Documents\FileController;
use App\Http\Controllers\School\DashboardController;
use App\Http\Controllers\School\Identity\AuditController;
use App\Http\Controllers\School\Identity\MemberController;
use App\Http\Controllers\School\Identity\RoleController;
use Illuminate\Support\Facades\Route;

// Area middleware (bootstrap/app.php): web, auth, active.user, school.context, subscription.access, two-factor.role.
// Names are prefixed "school." and URLs "/app". Each group declares the permission it needs with can:<permission>.

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('files/{file}', [FileController::class, 'show'])->middleware('throttle:downloads')->name('files.show');

Route::prefix('access')->name('access.')->group(function () {
    Route::middleware('can:access.members.view')->group(function () {
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
    });

    Route::post('members/invite', [MemberController::class, 'invite'])->middleware('can:access.members.invite')->name('members.invite');
    Route::delete('invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->middleware('can:access.members.invite')->name('invitations.revoke');

    Route::middleware('can:access.members.update')->group(function () {
        Route::put('members/{membership}/roles', [MemberController::class, 'updateRoles'])->name('members.roles');
        Route::post('members/{membership}/suspend', [MemberController::class, 'suspend'])->name('members.suspend');
        Route::post('members/{membership}/activate', [MemberController::class, 'activate'])->name('members.activate');
    });

    Route::middleware('can:access.roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::get('audit', AuditController::class)->middleware('can:access.audit.view')->name('audit.index');
});
