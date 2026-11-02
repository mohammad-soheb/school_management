<?php

namespace App\Services\Identity;

use App\Models\Identity\Role;
use App\Models\Identity\RolePermission;
use App\Models\Platform\School;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Identity\Permissions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create the five default roles (config/roles.php) for a new school, each with the permissions declared by
     * the modules (config/permissions/*.php). School Admin holds every permission dynamically, so it stores none.
     */
    public function seedDefaultRoles(School $school): void
    {
        $this->context->run($school, function () use ($school): void {
            foreach (config('roles') as $slug => $definition) {
                $role = Role::create([
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'description' => $definition['description'],
                    'is_system' => true,
                ]);

                if ($slug === 'school_admin') {
                    continue;
                }

                $this->insertPermissions($role, Permissions::defaultsFor($slug));
            }
        });
    }

    /**
     * Grant any default permissions that newer modules added since a school's roles were seeded
     * (php artisan erp:sync-permissions). Never removes anything a school customised.
     *
     * @return int number of permission rows added
     */
    public function grantMissingDefaults(School $school): int
    {
        return $this->context->run($school, function (): int {
            $added = 0;

            foreach (Role::query()->where('is_system', true)->where('slug', '!=', 'school_admin')->get() as $role) {
                $missing = array_diff(Permissions::defaultsFor($role->slug), $role->permissionNames());
                $this->insertPermissions($role, array_values($missing));
                $added += count($missing);
            }

            return $added;
        });
    }

    /** @param  array{name: string, description?: ?string}  $data */
    public function create(array $data, User $actor): Role
    {
        $slug = Str::slug($data['name'], '_');

        if (Role::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }

        $role = Role::create(['name' => $data['name'], 'slug' => $slug, 'description' => $data['description'] ?? null, 'is_system' => false]);
        $this->audit->record('role.created', $role, new: ['name' => $role->name], actor: $actor);

        return $role;
    }

    /**
     * Replace a role's permissions. Unknown names are rejected; School Admin cannot be edited.
     *
     * @param  list<string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions, User $actor): void
    {
        if ($role->isSchoolAdmin()) {
            throw ValidationException::withMessages(['permissions' => 'School Admin always has every permission and cannot be edited.']);
        }

        $permissions = array_values(array_unique($permissions));
        $unknown = array_filter($permissions, fn (string $p): bool => ! Permissions::isSchoolPermission($p));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'Unknown permission: '.implode(', ', $unknown)]);
        }

        DB::transaction(function () use ($role, $permissions, $actor): void {
            $before = $role->permissionNames();

            RolePermission::query()->where('role_id', $role->getKey())->delete();
            $this->insertPermissions($role, $permissions);

            $this->audit->record(
                'role.permissions_changed',
                $role,
                ['permissions' => array_values(array_diff($before, $permissions))],
                ['permissions' => array_values(array_diff($permissions, $before))],
                actor: $actor,
            );
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'Built-in roles cannot be deleted.']);
        }

        if (DB::table('membership_role')->where('role_id', $role->getKey())->exists()) {
            throw ValidationException::withMessages(['role' => 'This role is still assigned to users. Reassign them first.']);
        }

        DB::transaction(function () use ($role, $actor): void {
            RolePermission::query()->where('role_id', $role->getKey())->delete();
            $role->delete();
            $this->audit->record('role.deleted', $role, ['name' => $role->name], actor: $actor);
        });
    }

    /** @param  list<string>  $permissions */
    private function insertPermissions(Role $role, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        $now = now();

        RolePermission::query()->insert(array_map(fn (string $permission): array => [
            'school_id' => $role->school_id,
            'role_id' => $role->getKey(),
            'permission' => $permission,
            'created_at' => $now,
            'updated_at' => $now,
        ], $permissions));
    }
}
