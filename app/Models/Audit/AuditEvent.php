<?php

namespace App\Models\Audit;

use App\Models\User;
use App\Support\Tenancy\SchoolScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only record of a sensitive action. Rows can be created, never changed or deleted.
 * school_id is NULL for platform-level events, so this model is scoped like tenant data but allows NULL on create;
 * platform code that must read every school's events uses TenantContext::withoutTenant().
 */
#[Fillable(['school_id', 'actor_id', 'event', 'subject_type', 'subject_id', 'old', 'new', 'meta'])]
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (AuditEvent $event): void {
            $event->school_id ??= app(TenantContext::class)->id();
        });

        static::updating(fn () => throw new LogicException('Audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'old' => 'array',
            'new' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
