<?php

namespace Database\Factories\Students;

use App\Enums\Students\GuardianLinkStatus;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentGuardian;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<StudentGuardian>
 */
class StudentGuardianFactory extends TenantFactory
{
    protected $model = StudentGuardian::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'guardian_id' => fn (array $a) => Guardian::factory()->create(['school_id' => $a['school_id']])->id,
            'relation' => 'father',
            'is_primary' => true,
            'status' => GuardianLinkStatus::Active,
            'verified_at' => now(),
        ];
    }

    /** A link the school has not verified yet: the parent portal must ignore it. */
    public function unverified(): static
    {
        return $this->state(fn () => ['verified_at' => null]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => GuardianLinkStatus::Revoked, 'revoked_at' => now()]);
    }
}
