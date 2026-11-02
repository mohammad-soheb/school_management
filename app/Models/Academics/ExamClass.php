<?php

namespace App\Models\Academics;

use App\Models\SchoolSetup\SchoolClass;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A class the exam applies to. Table: exam_classes. */
#[Fillable(['exam_id', 'school_class_id'])]
class ExamClass extends Model
{
    use BelongsToSchool;

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
