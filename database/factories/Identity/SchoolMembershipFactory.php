<?php

namespace Database\Factories\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\SchoolMembership;
use App\Models\User;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<SchoolMembership>
 */
class SchoolMembershipFactory extends TenantFactory
{
    protected $model = SchoolMembership::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'user_id' => User::factory(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::Suspended]);
    }
}
