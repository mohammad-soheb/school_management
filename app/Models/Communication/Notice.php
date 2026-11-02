<?php

namespace App\Models\Communication;

use App\Enums\Communication\NoticeStatus;
use App\Models\Documents\StoredFile;
use App\Models\User;
use App\Policies\Communication\NoticePolicy;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Communication\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A notice / circular (FR-506). Who sees it is decided by its audience rows (NoticeAudience) and the
 * publication window; see App\Services\Communication\NoticeAudienceResolver.
 */
#[Fillable(['title', 'body', 'status', 'is_pinned', 'publish_at', 'expires_at', 'published_at', 'published_by', 'created_by', 'updated_by', 'archived_at', 'archived_by'])]
#[UsePolicy(NoticePolicy::class)]
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => NoticeStatus::class,
            'is_pinned' => 'boolean',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NoticeFactory
    {
        return NoticeFactory::new();
    }

    /**
     * Published, already past publish_at and not yet expired.
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeVisibleNow(Builder $query): void
    {
        $now = now();

        $query->where('status', NoticeStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    /** @param  Builder<Notice>  $query */
    public function scopeBoardOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByRaw('COALESCE(publish_at, created_at) DESC')->orderByDesc('id');
    }

    public function isVisibleNow(): bool
    {
        return $this->status === NoticeStatus::Published
            && ($this->publish_at === null || $this->publish_at->lte(now()))
            && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    /** Display state: draft | scheduled | live | expired | archived. */
    public function state(): string
    {
        return match (true) {
            $this->status === NoticeStatus::Draft => 'draft',
            $this->status === NoticeStatus::Archived => 'archived',
            $this->expires_at !== null && $this->expires_at->lte(now()) => 'expired',
            $this->publish_at !== null && $this->publish_at->gt(now()) => 'scheduled',
            default => 'live',
        };
    }

    public function stateLabel(): string
    {
        return ucfirst($this->state());
    }

    public function stateTone(): string
    {
        return match ($this->state()) {
            'draft' => 'warning',
            'scheduled' => 'info',
            'live' => 'success',
            default => 'neutral',
        };
    }

    /** @return HasMany<NoticeAudience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(NoticeAudience::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return MorphMany<StoredFile, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'attachable')->where('purpose', 'notice')->orderBy('id');
    }
}
