<?php

namespace App\Support\Audit;

use App\Models\Audit\AuditEvent;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records who did what, when (FR-107). Services call it for attendance, fees, marks, permissions, subscriptions.
 *
 *   $this->audit->record('student.status_changed', $student, ['status' => 'active'], ['status' => 'withdrawn']);
 *
 * Event names are "<noun>.<past-tense verb>". Never pass passwords, tokens, 2FA secrets or document contents:
 * known sensitive keys are redacted defensively, but callers should not rely on that.
 */
final class AuditLogger
{
    /** Keys whose values are replaced with a marker before being stored. */
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'current_password', 'token', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'recovery_code', 'code', 'secret'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $meta
     */
    public function record(string $event, ?Model $subject = null, array $old = [], array $new = [], array $meta = [], ?User $actor = null, ?int $schoolId = null): AuditEvent
    {
        $actor ??= Auth::user();

        // Prefer the explicit school, then the active one, then the subject's own school_id (when it has that column).
        $subjectSchoolId = $subject !== null && array_key_exists('school_id', $subject->getAttributes())
            ? $subject->getAttribute('school_id')
            : null;

        return AuditEvent::create([
            'school_id' => $schoolId ?? $this->context->id() ?? $subjectSchoolId,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old' => $old === [] ? null : $this->redact($old),
            'new' => $new === [] ? null : $this->redact($new),
            'meta' => $this->meta($meta),
        ]);
    }

    /**
     * Diff two attribute arrays into [old, new] containing only the keys that changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $old = $new = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    private function meta(array $meta): ?array
    {
        $request = app()->bound('request') ? request() : null;

        if ($request !== null && ! app()->runningInConsole()) {
            $meta += ['ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)];
        }

        return $meta === [] ? null : $this->redact($meta);
    }
}
