<?php

use App\Http\Controllers\School\Attendance\AttendanceController;
use App\Http\Controllers\School\Attendance\AttendanceReportController;
use App\Http\Controllers\School\Attendance\AttendanceSheetController;
use App\Http\Controllers\School\Attendance\StaffAttendanceController;
use Illuminate\Support\Facades\Route;

// Attendance (master plan Phase 3, FR-301..FR-307). Names "school.attendance.*", URLs "/app/attendance/...".
// Section-level rules (a teacher's assigned classes) are enforced by form requests / policies on top of can:.

Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::middleware('can:attendance.students.access')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('sections/{section}', [AttendanceController::class, 'mark'])->name('mark');
        Route::get('sheets/{sheet}', [AttendanceSheetController::class, 'show'])->name('sheets.show');
    });

    Route::post('sections/{section}', [AttendanceController::class, 'store'])
        ->middleware(['can:attendance.students.mark_any', 'throttle:60,1'])
        ->name('store');

    Route::put('sheets/{sheet}', [AttendanceSheetController::class, 'correct'])
        ->middleware('can:attendance.students.correct')
        ->name('sheets.correct');

    Route::get('staff', [StaffAttendanceController::class, 'index'])->middleware('can:attendance.staff.access')->name('staff.index');
    Route::middleware('can:attendance.staff.mark')->group(function () {
        Route::post('staff', [StaffAttendanceController::class, 'store'])->name('staff.store');
        Route::put('staff', [StaffAttendanceController::class, 'correct'])->name('staff.correct');
    });

    Route::prefix('reports')->name('reports.')->middleware('can:attendance.reports.view')->group(function () {
        Route::get('/', [AttendanceReportController::class, 'daily'])->name('daily');
        Route::get('daily.csv', [AttendanceReportController::class, 'dailyExport'])->name('daily.export');
        Route::get('monthly', [AttendanceReportController::class, 'monthly'])->name('monthly');
        Route::get('monthly/print', [AttendanceReportController::class, 'monthlyPrint'])->name('monthly.print');
        Route::get('monthly.csv', [AttendanceReportController::class, 'monthlyExport'])->name('monthly.export');
        Route::get('students', [AttendanceReportController::class, 'students'])->name('students');
        Route::get('students.csv', [AttendanceReportController::class, 'studentsExport'])->name('students.export');

        Route::middleware('can:attendance.staff.access')->group(function () {
            Route::get('staff', [AttendanceReportController::class, 'staff'])->name('staff');
            Route::get('staff.csv', [AttendanceReportController::class, 'staffExport'])->name('staff.export');
        });
    });
});
