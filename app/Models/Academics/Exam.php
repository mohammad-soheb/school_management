<?php

namespace App\Models\Academics;

use App\Enums\Academics\ExamStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\User;
use App\Policies\Academics\ExamPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An exam (e.g. "Half-Yearly") in one academic year, for a set of classes. Workflow: see ExamStatus. */
#[Fillable(['academic_year_id', 'grading_scheme_id', 'name', 'term', 'starts_on', 'ends_on', 'status', 'description', 'marks_opened_at', 'locked_at', 'locked_by', 'created_by', 'updated_by'])]
#[UsePolicy(ExamPolicy::class)]
class Exam extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'marks_opened_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<GradingScheme, $this> */
    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /** @return HasMany<ExamClass, $this> */
    public function examClasses(): HasMany
    {
        return $this->hasMany(ExamClass::class);
    }

    /** @return BelongsToMany<SchoolClass, $this> */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_classes', 'exam_id', 'school_class_id')
            ->orderBy('school_classes.sort_order')
            ->orderBy('school_classes.name');
    }

    /** @return HasMany<ExamSubject, $this> */
    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ResultPublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(ResultPublication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isDraft(): bool
    {
        return $this->status === ExamStatus::Draft;
    }

    public function isLocked(): bool
    {
        return $this->status === ExamStatus::Locked;
    }

    /** "12 Sep – 24 Sep 2026" */
    public function dateRange(): string
    {
        $from = $this->starts_on;
        $to = $this->ends_on;

        if ($from->equalTo($to)) {
            return $from->format('d M Y');
        }

        return $from->format($from->year === $to->year ? 'd M' : 'd M Y').' – '.$to->format('d M Y');
    }
}
