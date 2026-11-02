<?php

namespace App\Models\SchoolSetup;

use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\SchoolSetup\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A grade / standard (e.g. "Class 5"). Table: school_classes. */
#[Fillable(['name', 'code', 'sort_order', 'next_class_id'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use BelongsToSchool, HasFactory;

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function nextClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'next_class_id');
    }

    /** @return BelongsToMany<Subject, $this> */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects', 'school_class_id', 'subject_id')
            ->withPivot(['school_id', 'is_optional', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
