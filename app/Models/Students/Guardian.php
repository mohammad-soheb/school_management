<?php

namespace App\Models\Students;

use App\Enums\Students\GuardianRelation;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Students\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name', 'relation', 'phone', 'email', 'occupation', 'address'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return ['relation' => GuardianRelation::class];
    }

    /** The parent-portal login, once the guardian has accepted an invitation. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians', 'guardian_id', 'student_id')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'school_id', 'relation', 'is_primary', 'is_emergency_contact', 'status', 'verified_at', 'verified_by', 'revoked_at'])
            ->withTimestamps();
    }
}
