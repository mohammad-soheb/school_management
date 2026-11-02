<?php

namespace App\Models\SchoolSetup;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Subject taught in a class. Table: class_subjects. */
#[Fillable(['school_class_id', 'subject_id', 'is_optional', 'sort_order'])]
class ClassSubject extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['is_optional' => 'boolean'];
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
