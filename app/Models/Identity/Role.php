<?php

namespace App\Models\Identity;

use App\Support\Identity\Permissions;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Identity\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** @return HasMany<RolePermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** The School Admin role is fixed: it always holds every school permission and cannot be edited. */
    public function isSchoolAdmin(): bool
    {
        return $this->is_system && $this->slug === 'school_admin';
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        if ($this->isSchoolAdmin()) {
            return Permissions::allSchool();
        }

        return $this->permissions()->pluck('permission')->all();
    }
}
