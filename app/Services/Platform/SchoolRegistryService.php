<?php

namespace App\Services\Platform;

use App\Enums\Platform\SchoolStatus;
use App\Models\Identity\Role;
use App\Models\Platform\School;
use App\Models\User;
use App\Services\Identity\InvitationService;
use App\Services\Identity\RoleService;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Super Admin operations on the customer school registry (FR-101, FR-801, FR-802). */
class SchoolRegistryService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly RoleService $roles,
        private readonly InvitationService $invitations,
        private readonly AuditLogger $audit,
    ) {}

    /** @return LengthAwarePaginator<int, School> */
    public function paginate(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return School::query()
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('city', 'like', $like));
            })
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Headline numbers for the platform dashboard. @return array{schools: int, active: int, suspended: int, users: int} */
    public function stats(): array
    {
        $byStatus = School::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'schools' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[SchoolStatus::Active->value] ?? 0),
            'suspended' => (int) ($byStatus[SchoolStatus::Suspended->value] ?? 0),
            'users' => User::query()->where('is_platform_admin', false)->count(),
        ];
    }

    /**
     * Create a school, give it the default roles and invite its first School Admin.
     *
     * @param  array<string, mixed>  $data  school fields plus admin_email
     * @return array{school: School, invitation_url: string}
     */
    public function create(array $data, User $actor): array
    {
        $adminEmail = $data['admin_email'];
        unset($data['admin_email']);

        $data['code'] = Str::lower($data['code'] ?? Str::slug($data['name']));
        $data['status'] = SchoolStatus::Active;

        return DB::transaction(function () use ($data, $adminEmail, $actor): array {
            $school = School::create($data);

            $this->roles->seedDefaultRoles($school);

            $invite = $this->context->run($school, function () use ($adminEmail, $actor): array {
                $adminRole = Role::query()->where('slug', 'school_admin')->firstOrFail();

                return $this->invitations->invite($adminEmail, [$adminRole->getKey()], $actor);
            });

            $this->audit->record('school.created', $school, new: ['name' => $school->name, 'code' => $school->code], actor: $actor, schoolId: $school->getKey());

            return ['school' => $school, 'invitation_url' => $invite['url']];
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(School $school, array $data, User $actor): School
    {
        $school->fill($data);
        [$old, $new] = $this->audit->diff($school->getOriginal(), $school->getDirty());

        $school->save();

        if ($new !== []) {
            $this->audit->record('school.updated', $school, $old, $new, actor: $actor, schoolId: $school->getKey());
        }

        return $school;
    }

    public function suspend(School $school, string $reason, User $actor): void
    {
        $school->forceFill(['status' => SchoolStatus::Suspended])->save();
        $this->audit->record('school.suspended', $school, new: ['reason' => $reason], actor: $actor, schoolId: $school->getKey());
    }

    public function activate(School $school, User $actor): void
    {
        $school->forceFill(['status' => SchoolStatus::Active])->save();
        $this->audit->record('school.activated', $school, actor: $actor, schoolId: $school->getKey());
    }
}
