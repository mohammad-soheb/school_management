<?php

namespace App\Models\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Identity\SchoolMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A user's seat in one school, with one or more roles. Every request acts through exactly one membership. */
#[Fillable(['user_id', 'status', 'invited_by', 'joined_at', 'last_active_at'])]
class SchoolMembership extends Model
{
    /** @use HasFactory<SchoolMembershipFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role', 'membership_id', 'role_id')->withTimestamps();
    }

    /** Replace this membership's roles. Always use this: the pivot rows must carry the school id. */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync(array_fill_keys($roleIds, ['school_id' => $this->school_id]));
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** A parent/guardian seat (holds no staff permissions; the parent portal is gated by guardian links). */
    public function isGuardian(): bool
    {
        return $this->hasRole('parent');
    }

    /**
     * Union of the permissions of every role on this membership.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $names = [];

        foreach ($this->roles()->get() as $role) {
            foreach ($role->permissionNames() as $permission) {
                $names[$permission] = true;
            }
        }

        return array_keys($names);
    }
}
