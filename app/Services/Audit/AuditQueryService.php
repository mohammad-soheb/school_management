<?php

namespace App\Services\Audit;

use App\Models\Audit\AuditEvent;
use Illuminate\Pagination\LengthAwarePaginator;

/** Read side of the audit trail, for the school audit log screen (scoped to the active school by AuditEvent). */
class AuditQueryService
{
    /**
     * @param  array{event?: ?string, actor_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return LengthAwarePaginator<int, AuditEvent>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AuditEvent::query()
            ->with('actor')
            ->when($filters['event'] ?? null, fn ($q, string $event) => $q->where('event', 'like', addcslashes($event, '%_\\').'%'))
            ->when($filters['actor_id'] ?? null, fn ($q, int $actor) => $q->where('actor_id', $actor))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
