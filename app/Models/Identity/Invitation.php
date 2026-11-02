<?php

namespace App\Models\Identity;

use App\Models\Students\Guardian;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An emailed (or hand-delivered) link that lets a person create their login for one school with preset roles.
 * Only the SHA-256 of the token is stored; the plain token exists once, in the link shown to the inviter.
 */
#[Fillable(['email', 'token_hash', 'role_ids', 'guardian_id', 'invited_by', 'expires_at'])]
class Invitation extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @param  Builder<Invitation>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'Accepted',
            $this->revoked_at !== null => 'Revoked',
            $this->expires_at->isPast() => 'Expired',
            default => 'Pending',
        };
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<Guardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
