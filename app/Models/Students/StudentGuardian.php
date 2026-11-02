<?php

namespace App\Models\Students;

use App\Enums\Students\GuardianLinkStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Students\StudentGuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link between a student and a guardian. The parent portal honours a link only while it is active AND verified
 * by the school (verified_at set) - see scopeGrantingAccess().
 */
#[Fillable(['student_id', 'guardian_id', 'relation', 'is_primary', 'is_emergency_contact', 'status', 'verified_at', 'verified_by', 'revoked_at'])]
class StudentGuardian extends Pivot
{
    /** @use HasFactory<StudentGuardianFactory> */
    use BelongsToSchool, HasFactory;

    public $incrementing = true;

    protected $table = 'student_guardians';

    protected function casts(): array
    {
        return [
            'status' => GuardianLinkStatus::class,
            'is_primary' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Links that currently entitle the guardian's login to see the student. @param  Builder<StudentGuardian>  $query */
    public function scopeGrantingAccess(Builder $query): void
    {
        $query->where('status', GuardianLinkStatus::Active->value)->whereNotNull('verified_at');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Guardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
