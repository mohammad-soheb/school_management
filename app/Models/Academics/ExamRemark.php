<?php

namespace App\Models\Academics;

use App\Models\Students\StudentEnrollment;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Class teacher's remark for a student in an exam (printed on the report card). */
#[Fillable(['exam_id', 'student_enrollment_id', 'remark', 'entered_by', 'updated_by'])]
class ExamRemark extends Model
{
    use BelongsToSchool;

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
