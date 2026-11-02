<?php

namespace App\Models\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\SchoolSetup\SchoolHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A holiday, event or exam period on the school calendar. Table: school_holidays. */
#[Fillable(['academic_year_id', 'title', 'starts_on', 'ends_on', 'type'])]
class SchoolHoliday extends Model
{
    /** @use HasFactory<SchoolHolidayFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'type' => HolidayType::class,
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
