<?php

namespace App\Models\Students;

use App\Enums\Shared\Gender;
use App\Enums\Students\StudentStatus;
use App\Support\Tenancy\BelongsToSchool;
use App\Support\Tenancy\TenantContext;
use Database\Factories\Students\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Permanent student identity. Class, section and roll number belong to StudentEnrollment (one per academic year);
 * never store them here, and never delete a student - change the status instead (history must survive).
 */
#[Fillable(['admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'blood_group', 'admission_date', 'status', 'phone', 'email', 'address', 'previous_school', 'photo_file_id'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return HasMany<StudentEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** The enrollment in the school's current academic year (null if not enrolled this year). */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->where('academic_year_id', app(TenantContext::class)->academicYear()?->getKey());
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'school_id', 'relation', 'is_primary', 'is_emergency_contact', 'status', 'verified_at', 'verified_by', 'revoked_at'])
            ->withTimestamps();
    }
}
