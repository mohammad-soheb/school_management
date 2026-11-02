<?php

use App\Http\Controllers\School\SchoolSetup\AcademicYearController;
use App\Http\Controllers\School\SchoolSetup\ClassController;
use App\Http\Controllers\School\SchoolSetup\HolidayController;
use App\Http\Controllers\School\SchoolSetup\SchoolProfileController;
use App\Http\Controllers\School\SchoolSetup\SchoolSettingsController;
use App\Http\Controllers\School\SchoolSetup\SectionController;
use App\Http\Controllers\School\SchoolSetup\SetupOverviewController;
use App\Http\Controllers\School\SchoolSetup\SubjectController;
use Illuminate\Support\Facades\Route;

// School setup (FR-201, FR-202). Names "school.setup.*", URLs "/app/setup/*".
// Reading the structure needs setup.view; every write needs the area's own permission (and its policy).

Route::prefix('setup')->name('setup.')->group(function () {
    Route::middleware('can:setup.view')->group(function () {
        Route::get('/', SetupOverviewController::class)->name('index');
        Route::get('years', [AcademicYearController::class, 'index'])->name('years.index');
        Route::get('classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    });

    Route::middleware('can:setup.profile.update')->group(function () {
        Route::get('profile', [SchoolProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [SchoolProfileController::class, 'update'])->name('profile.update');
    });

    Route::middleware('can:setup.settings.manage')->group(function () {
        Route::get('settings', [SchoolSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SchoolSettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:setup.years.manage')->group(function () {
        Route::get('years/create', [AcademicYearController::class, 'create'])->name('years.create');
        Route::post('years', [AcademicYearController::class, 'store'])->name('years.store');
        Route::get('years/{year}/edit', [AcademicYearController::class, 'edit'])->name('years.edit');
        Route::put('years/{year}', [AcademicYearController::class, 'update'])->name('years.update');
        Route::post('years/{year}/current', [AcademicYearController::class, 'setCurrent'])->name('years.current');
    });

    Route::middleware('can:setup.years.lock')->group(function () {
        Route::post('years/{year}/lock', [AcademicYearController::class, 'lock'])->name('years.lock');
        Route::post('years/{year}/reopen', [AcademicYearController::class, 'reopen'])->name('years.reopen');
    });

    Route::middleware('can:setup.classes.manage')->group(function () {
        Route::get('classes/create', [ClassController::class, 'create'])->name('classes.create');
        Route::post('classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('classes/{class}/edit', [ClassController::class, 'edit'])->name('classes.edit');
        Route::put('classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{class}/activate', [ClassController::class, 'activate'])->name('classes.activate');
        Route::post('classes/{class}/deactivate', [ClassController::class, 'deactivate'])->name('classes.deactivate');
        Route::put('classes/{class}/subjects', [ClassController::class, 'syncSubjects'])->name('classes.subjects');

        Route::post('classes/{class}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('sections/{section}/activate', [SectionController::class, 'activate'])->name('sections.activate');
        Route::post('sections/{section}/deactivate', [SectionController::class, 'deactivate'])->name('sections.deactivate');
    });

    Route::middleware('can:setup.subjects.manage')->group(function () {
        Route::get('subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
        Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('subjects/{subject}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::post('subjects/{subject}/activate', [SubjectController::class, 'activate'])->name('subjects.activate');
        Route::post('subjects/{subject}/deactivate', [SubjectController::class, 'deactivate'])->name('subjects.deactivate');
    });

    Route::middleware('can:setup.holidays.manage')->group(function () {
        Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::get('holidays/{holiday}/edit', [HolidayController::class, 'edit'])->name('holidays.edit');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    });

    // Read-only class page last, so "classes/create" is never captured by {class}.
    Route::get('classes/{class}', [ClassController::class, 'show'])->middleware('can:setup.view')->name('classes.show');
});
