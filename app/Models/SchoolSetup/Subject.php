<?php

namespace App\Models\SchoolSetup;

use App\Enums\SchoolSetup\SubjectType;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\SchoolSetup\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'type', 'sort_order', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SubjectType::class,
            'is_active' => 'boolean',
        ];
    }
}
