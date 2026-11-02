<?php

namespace App\Services\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\SchoolMembership;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SchoolContextService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, SchoolMembership> the user's active memberships with their school and roles */
    public function membershipsFor(User $user): Collection
    {
        return $this->context->withoutTenant(
            fn () => $user->activeMemberships()->with(['school', 'roles'])->get(),
        );
    }

    /**
     * Switch the session to another school. The school id comes from the browser, so it is only honoured if the
     * user has an ACTIVE membership there; anything else is rejected without revealing whether the school exists.
     */
    public function switchTo(User $user, int $schoolId, Session $session): SchoolMembership
    {
        $membership = $this->context->withoutTenant(
            fn () => $user->memberships()
                ->where('school_id', $schoolId)
                ->where('status', MembershipStatus::Active->value)
                ->with('school')
                ->first(),
        );

        if ($membership === null || ! $membership->school->isActive()) {
            throw ValidationException::withMessages(['school_id' => 'You do not have access to that school.']);
        }

        $session->put('school_id', $membership->school_id);
        $this->context->set($membership->school, $membership);

        $this->audit->record('school.switched', $membership->school, actor: $user);

        return $membership;
    }
}
