<?php

namespace App\Models;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\SchoolMembership;
use App\Support\Tenancy\SchoolScope;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin === true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * All of this user's school memberships. The relation deliberately ignores the active-school filter:
     * it is the user-centred view used to pick or switch school, not tenant data.
     *
     * @return HasMany<SchoolMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMembership::class)->withoutGlobalScope(SchoolScope::class);
    }

    /** @return HasMany<SchoolMembership, $this> */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active->value);
    }

    /**
     * Does the user hold $permission in the ACTIVE school? False when there is no active school or the active
     * membership belongs to someone else. School Admin holds every school permission (see Role::permissionNames).
     */
    public function hasPermission(string $permission): bool
    {
        $context = app(TenantContext::class);
        $membership = $context->membership();

        if ($membership === null || $membership->user_id !== $this->getKey()) {
            return false;
        }

        return in_array($permission, $context->permissions(), true);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('') ?: '?';
    }
}
