<?php

namespace App\Services\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\SchoolMembership;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** @return LengthAwarePaginator<int, SchoolMembership> members of the active school, optionally filtered by name or email */
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return SchoolMembership::query()
            ->with(['user', 'roles'])
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->whereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Replace a member's roles. A school can never be left without an active School Admin.
     *
     * @param  list<int>  $roleIds
     */
    public function updateRoles(SchoolMembership $membership, array $roleIds, User $actor): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        if ($roleIds === []) {
            throw ValidationException::withMessages(['roles' => 'Choose at least one role.']);
        }

        DB::transaction(function () use ($membership, $roleIds, $actor): void {
            // Lock the memberships so two concurrent changes cannot both remove "the other" admin.
            $this->lockMemberships();

            $before = $membership->roles()->pluck('roles.slug')->all();
            $membership->syncRoles($roleIds);
            $membership->unsetRelation('roles');
            $membership->load('roles');

            $this->assertAdminRemains();

            $this->audit->record(
                'membership.roles_changed',
                $membership,
                ['roles' => $before],
                ['roles' => $membership->roles->pluck('slug')->all()],
                actor: $actor,
            );
        });

        $this->context->forgetMemoized();
    }

    public function suspend(SchoolMembership $membership, User $actor): void
    {
        if ($membership->user_id === $actor->getKey()) {
            throw ValidationException::withMessages(['member' => 'You cannot suspend your own access.']);
        }

        $this->setStatus($membership, MembershipStatus::Suspended, 'membership.suspended', $actor);
    }

    public function activate(SchoolMembership $membership, User $actor): void
    {
        $this->setStatus($membership, MembershipStatus::Active, 'membership.activated', $actor);
    }

    private function setStatus(SchoolMembership $membership, MembershipStatus $status, string $event, User $actor): void
    {
        DB::transaction(function () use ($membership, $status, $event, $actor): void {
            $this->lockMemberships();

            $membership->forceFill(['status' => $status])->save();
            $this->assertAdminRemains();

            $this->audit->record($event, $membership, new: ['status' => $status->value], actor: $actor);
        });
    }

    private function lockMemberships(): void
    {
        SchoolMembership::query()->lockForUpdate()->get(['id']);
    }

    private function assertAdminRemains(): void
    {
        $admins = SchoolMembership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'school_admin')->where('is_system', true))
            ->count();

        if ($admins === 0) {
            throw ValidationException::withMessages(['member' => 'The school must keep at least one active School Admin.']);
        }
    }
}
