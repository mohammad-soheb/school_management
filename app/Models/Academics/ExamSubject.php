<?php

namespace App\Models\Academics;

use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Subject;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject examined in one class of an exam. With components, max_marks is the sum of the components' max marks.
 */
#[Fillable(['exam_id', 'school_class_id', 'subject_id', 'max_marks', 'pass_marks', 'counts_in_total', 'sort_order'])]
class ExamSubject extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'counts_in_total' => 'boolean',
        ];
    }

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

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<ExamComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(ExamComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Mark, $this> */
    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    /**
     * Definition used by the ResultCalculator. Load `subject` and `components` first.
     *
     * @return array{id: int, name: string, code: ?string, max: string, pass: string, counts_in_total: bool, components: list<array{id: int, name: string, max: string, pass: ?string}>}
     */
    public function rule(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'name' => $this->subject->name,
            'code' => $this->subject->code,
            'max' => (string) $this->max_marks,
            'pass' => (string) $this->pass_marks,
            'counts_in_total' => (bool) $this->counts_in_total,
            'components' => $this->components->map(fn (ExamComponent $c) => [
                'id' => (int) $c->getKey(),
                'name' => $c->name,
                'max' => (string) $c->max_marks,
                'pass' => $c->pass_marks !== null ? (string) $c->pass_marks : null,
            ])->values()->all(),
        ];
    }
}
