<?php

namespace App\Http\Requests\Attendance;

use App\Models\SchoolSetup\Section;

/** CSV export / print of a class report: the same filters, but a section is required. */
class SectionReportExportRequest extends AttendanceReportRequest
{
    protected bool $sectionRequired = true;

    public function requiredSection(): Section
    {
        return $this->section() ?? abort(404);
    }
}
