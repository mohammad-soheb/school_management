<?php

use App\Http\Controllers\School\Students\GuardianController;
use App\Http\Controllers\School\Students\StudentController;
use App\Http\Controllers\School\Students\StudentDocumentController;
use App\Http\Controllers\School\Students\StudentGuardianController;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use Illuminate\Support\Facades\Route;

// People module: students and guardians. Names "school.students.*" / "school.guardians.*", URLs /app/students, /app/guardians.
// Layers: can: middleware here -> FormRequest::authorize() -> StudentPolicy / GuardianPolicy / StudentGuardianPolicy
// (record rules: teacher sections, same school). Nested children are bound through their parent (scopeBindings), so
// another student's link or document id is a 404.

Route::prefix('students')->name('students.')->group(function () {
    // students.view (teachers: assigned sections only) or students.view_all.
    Route::middleware('can:viewAny,'.Student::class)->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('lookup', [StudentController::class, 'lookup'])->middleware('throttle:60,1')->name('lookup');
    });

    Route::middleware('can:students.create')->group(function () {
        Route::get('create', [StudentController::class, 'create'])->name('create');
        Route::post('/', [StudentController::class, 'store'])->name('store');
    });

    Route::middleware('can:viewAny,'.Student::class)->group(function () {
        Route::get('{student}', [StudentController::class, 'show'])->name('show');
        Route::get('{student}/guardians', [StudentController::class, 'guardians'])->name('guardians');
        Route::get('{student}/history', [StudentController::class, 'history'])->name('history');
    });

    Route::middleware('can:students.update')->group(function () {
        Route::get('{student}/edit', [StudentController::class, 'edit'])->name('edit');
        Route::put('{student}', [StudentController::class, 'update'])->name('update');
        Route::post('{student}/photo', [StudentController::class, 'photo'])->name('photo');
        Route::delete('{student}/photo', [StudentController::class, 'removePhoto'])->name('photo.destroy');
    });

    Route::post('{student}/status', [StudentController::class, 'changeStatus'])->middleware('can:students.status')->name('status');

    Route::get('{student}/documents', [StudentDocumentController::class, 'index'])->middleware('can:students.documents.view')->name('documents.index');
    Route::middleware('can:students.documents.upload')->scopeBindings()->group(function () {
        Route::post('{student}/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
        Route::delete('{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->name('documents.destroy');
    });

    Route::middleware('can:students.guardians.manage')->scopeBindings()->group(function () {
        Route::post('{student}/guardians', [StudentGuardianController::class, 'store'])->name('guardians.store');
        Route::put('{student}/guardians/{guardianLink}', [StudentGuardianController::class, 'update'])->name('guardians.update');
        Route::post('{student}/guardians/{guardianLink}/verify', [StudentGuardianController::class, 'verify'])->name('guardians.verify');
        Route::post('{student}/guardians/{guardianLink}/revoke', [StudentGuardianController::class, 'revoke'])->name('guardians.revoke');
    });
});

Route::prefix('guardians')->name('guardians.')->group(function () {
    // Used by the admission and link forms; the controller allows students.create or students.guardians.manage.
    Route::get('lookup', [GuardianController::class, 'lookup'])->middleware('throttle:60,1')->name('lookup');

    Route::middleware('can:viewAny,'.Guardian::class)->group(function () {
        Route::get('/', [GuardianController::class, 'index'])->name('index');
        Route::get('{guardian}', [GuardianController::class, 'show'])->name('show');
    });

    Route::middleware('can:students.guardians.manage')->group(function () {
        Route::get('{guardian}/edit', [GuardianController::class, 'edit'])->name('edit');
        Route::put('{guardian}', [GuardianController::class, 'update'])->name('update');
        Route::post('{guardian}/invite', [GuardianController::class, 'invite'])->middleware('throttle:20,1')->name('invite');
    });
});
