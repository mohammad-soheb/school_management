<?php

namespace App\Models\Promotion;

use App\Enums\Promotion\PromotionItemState;
use App\Enums\Promotion\PromotionOutcome;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Policies\Promotion\PromotionRunItemPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One student's year-end decision inside a promotion run. */
#[Fillable(['promotion_run_id', 'student_enrollment_id', 'student_id', 'from_class_id', 'outcome', 'target_class_id', 'target_section_id', 'target_roll_no', 'note', 'state', 'skip_reason', 'target_enrollment_id', 'previous_student_status', 'dues_carried_paise', 'applied_at', 'corrected_at'])]
#[UsePolicy(PromotionRunItemPolicy::class)]
class PromotionRunItem extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'outcome' => PromotionOutcome::class,
            'state' => PromotionItemState::class,
            'dues_carried_paise' => 'integer',
            'applied_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->state === PromotionItemState::Pending;
    }

    public function isApplied(): bool
    {
        return $this->state === PromotionItemState::Applied;
    }

    /** @return BelongsTo<PromotionRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PromotionRun::class, 'promotion_run_id');
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function targetEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'target_enrollment_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function targetClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'target_class_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function targetSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'target_section_id');
    }
}
