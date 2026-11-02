<?php

namespace App\Models\SchoolSetup;

use App\Models\Staff\StaffProfile;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\SchoolSetup\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_class_id', 'name', 'capacity', 'class_teacher_id', 'sort_order'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToSchool, HasFactory;

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'class_teacher_id');
    }

    /** "Class 5 - A". Load schoolClass first to avoid an extra query per row. */
    public function label(): string
    {
        return trim(($this->schoolClass?->name ?? '').' - '.$this->name, ' -');
    }
}
