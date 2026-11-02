<?php

namespace App\Models\Platform;

use App\Enums\Platform\SchoolStatus;
use App\Models\Identity\SchoolMembership;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\Platform\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

/**
 * A customer school (the tenant). Platform-level model: not scoped to a school; only the Super Admin
 * backoffice lists or edits schools.
 */
#[Fillable(['name', 'code', 'board', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'pincode', 'status', 'settings'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'settings' => 'array',
        ];
    }

    /** @return HasMany<SchoolMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMembership::class)->withoutGlobalScope(SchoolScope::class);
    }

    public function isActive(): bool
    {
        return $this->status === SchoolStatus::Active;
    }

    /**
     * A per-school setting by dot path ("attendance.leave_counts_as"). Falls back to the module defaults in
     * config/school_defaults/*.php, then to $default.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $key, fn () => config("school_defaults.{$key}", $default));
    }

    /** Is an optional module (features.salary, features.certificates ...) switched on for this school? */
    public function hasFeature(string $feature): bool
    {
        return (bool) $this->setting("features.{$feature}", false);
    }

    /** URL of the school logo (served through the authorised file route), or null. */
    public function logoUrl(): ?string
    {
        if ($this->logo_file_id === null || ! Route::has('school.files.show')) {
            return null;
        }

        return route('school.files.show', $this->logo_file_id);
    }
}
