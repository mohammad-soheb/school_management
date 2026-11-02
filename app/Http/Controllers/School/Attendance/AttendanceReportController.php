<?php

namespace App\Http\Controllers\School\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReportRequest;
use App\Http\Requests\Attendance\SectionReportExportRequest;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Attendance\CsvExport;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Attendance reports (FR-305, FR-306): daily, monthly register, student-wise, staff monthly, CSV and print. */
class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $reports) {}

    public function daily(AttendanceReportRequest $request): View
    {
        return view('school.attendance.reports.daily', [
            'report' => $this->reports->daily($request->user(), $request->date()),
            'rule' => $this->reports->calculator()->describe(),
        ]);
    }

    public function dailyExport(AttendanceReportRequest $request): StreamedResponse
    {
        return CsvExport::download($this->reports->dailyCsv($request->user(), $request->date()));
    }

    public function monthly(AttendanceReportRequest $request): View
    {
        $section = $request->section();

        return view('school.attendance.reports.monthly', [
            'sections' => $this->reports->sectionOptions($request->user()),
            'month' => $request->month(),
            'report' => $section ? $this->reports->monthly($section, $request->month()) : null,
        ]);
    }

    public function monthlyPrint(SectionReportExportRequest $request): View
    {
        return view('school.attendance.reports.monthly-print', [
            'report' => $this->reports->monthly($request->requiredSection(), $request->month()),
        ]);
    }

    public function monthlyExport(SectionReportExportRequest $request): StreamedResponse
    {
        return CsvExport::download($this->reports->monthlyCsv($request->requiredSection(), $request->month()));
    }

    public function students(AttendanceReportRequest $request): View
    {
        $section = $request->section();

        return view('school.attendance.reports.students', [
            'sections' => $this->reports->sectionOptions($request->user()),
            'from' => $request->from(),
            'to' => $request->to(),
            'report' => $section ? $this->reports->studentWise($section, $request->from(), $request->to()) : null,
        ]);
    }

    public function studentsExport(SectionReportExportRequest $request): StreamedResponse
    {
        return CsvExport::download($this->reports->studentWiseCsv($request->requiredSection(), $request->from(), $request->to()));
    }

    public function staff(AttendanceReportRequest $request): View
    {
        return view('school.attendance.reports.staff', ['report' => $this->reports->staffMonthly($request->month())]);
    }

    public function staffExport(AttendanceReportRequest $request): StreamedResponse
    {
        return CsvExport::download($this->reports->staffMonthlyCsv($request->month()));
    }
}
