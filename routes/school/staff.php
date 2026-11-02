<?php

use App\Http\Controllers\School\Staff\StaffController;
use App\Http\Controllers\School\Staff\StaffDocumentController;
use App\Http\Controllers\School\Staff\TeacherAssignmentController;
use Illuminate\Support\Facades\Route;

// People module: staff profiles and teacher assignments. Names "school.staff.*", URLs /app/staff.
// Layers: can: middleware here -> FormRequest::authorize() -> StaffProfilePolicy / TeacherAssignmentPolicy.

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('assignments', [TeacherAssignmentController::class, 'index'])->middleware('can:staff.view')->name('assignments.index');

    Route::middleware('can:staff.create')->group(function () {
        Route::get('create', [StaffController::class, 'create'])->name('create');
        Route::post('/', [StaffController::class, 'store'])->name('store');
    });

    Route::middleware('can:staff.view')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::get('{staff}', [StaffController::class, 'show'])->name('show');
    });

    Route::middleware('can:staff.update')->scopeBindings()->group(function () {
        Route::get('{staff}/edit', [StaffController::class, 'edit'])->name('edit');
        Route::put('{staff}', [StaffController::class, 'update'])->name('update');
        Route::post('{staff}/status', [StaffController::class, 'changeStatus'])->name('status');
        Route::post('{staff}/photo', [StaffController::class, 'photo'])->name('photo');
        Route::post('{staff}/login', [StaffController::class, 'linkUser'])->name('login.link');
        Route::delete('{staff}/login', [StaffController::class, 'unlinkUser'])->name('login.unlink');
        Route::post('{staff}/documents', [StaffDocumentController::class, 'store'])->name('documents.store');
        Route::delete('{staff}/documents/{document}', [StaffDocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Inviting needs both staff.update and access.members.invite (InviteStaffRequest -> StaffProfilePolicy::invite).
    Route::post('{staff}/invite', [StaffController::class, 'invite'])->middleware(['can:staff.update', 'can:access.members.invite', 'throttle:20,1'])->name('invite');

    Route::middleware('can:staff.assignments.manage')->scopeBindings()->group(function () {
        Route::post('{staff}/assignments', [TeacherAssignmentController::class, 'store'])->name('assignments.store');
        Route::delete('{staff}/assignments/{assignment}', [TeacherAssignmentController::class, 'destroy'])->name('assignments.destroy');
    });
});
