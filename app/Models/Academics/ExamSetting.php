<?php

namespace App\Models\Academics;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Per-school overrides of config/school_defaults/exams.php (one row per school). Read through ExamSettings. */
#[Fillable(['values', 'updated_by'])]
class ExamSetting extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['values' => 'array'];
    }
}
