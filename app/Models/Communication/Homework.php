<?php

namespace App\Models\Communication;

use App\Enums\Communication\HomeworkStatus;
use App\Models\Documents\StoredFile;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use App\Models\User;
use App\Policies\Communication\HomeworkPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Communication\HomeworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A piece of homework / an assignment for a class (all sections) or one section, in one subject (FR-505).
 * Table: homework. created_by / updated_by record who set and last edited it (FR-508).
 */
#[Fillable(['academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'title', 'description', 'assigned_on', 'due_on', 'status', 'created_by', 'updated_by', 'archived_at', 'archived_by'])]
#[UsePolicy(HomeworkPolicy::class)]
class Homework extends Model
{
    /** @use HasFactory<HomeworkFactory> */
    use BelongsToSchool, HasFactory;

    protected $table = 'homework';

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'due_on' => 'date',
            'status' => HomeworkStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): HomeworkFactory
    {
        return HomeworkFactory::new();
    }

    /** @param  Builder<Homework>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', HomeworkStatus::Active->value);
    }

    /**
     * Homework that applies to a student in $sectionId of $classId: set for that section or for the whole class.
     *
     * @param  Builder<Homework>  $query
     */
    public function scopeForSection(Builder $query, int $classId, ?int $sectionId): void
    {
        $query->where('school_class_id', $classId)
            ->where(fn (Builder $q) => $q->whereNull('section_id')->when($sectionId, fn (Builder $q) => $q->orWhere('section_id', $sectionId)));
    }

    public function isArchived(): bool
    {
        return $this->status === HomeworkStatus::Archived;
    }

    /** Past its due date and still active (compared with the school's calendar day, not UTC). */
    public function isOverdue(): bool
    {
        return ! $this->isArchived() && $this->due_on->toDateString() < school_today()->toDateString();
    }

    public function isDueToday(): bool
    {
        return $this->due_on->toDateString() === school_today()->toDateString();
    }

    /** "Class 5 - A" or "Class 5 (all sections)". Eager load schoolClass and section first. */
    public function audienceLabel(): string
    {
        $class = $this->schoolClass?->name ?? '';

        return $this->section_id === null ? trim($class.' (all sections)') : trim($class.' - '.($this->section?->name ?? ''), ' -');
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return MorphMany<StoredFile, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'attachable')->where('purpose', 'homework')->orderBy('id');
    }
}
