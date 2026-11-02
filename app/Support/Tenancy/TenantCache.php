<?php

namespace App\Support\Tenancy;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cache access that cannot leak between schools: every key is prefixed with the school id and a per-school
 * version number. Bumping the version (flush) invalidates all of that school's cached values at once, which
 * works on any cache store (the database store has no tags).
 */
final class TenantCache
{
    public static function key(string $key, ?int $schoolId = null): string
    {
        $schoolId ??= app(TenantContext::class)->requireId();

        return sprintf('school:%d:v%d:%s', $schoolId, self::version($schoolId), $key);
    }

    public static function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget(self::key($key));
    }

    /** Invalidate everything cached for a school (call after permission, membership or bulk data changes). */
    public static function flush(?int $schoolId = null): void
    {
        $schoolId ??= app(TenantContext::class)->requireId();

        Cache::forever(self::versionKey($schoolId), self::version($schoolId) + 1);
    }

    private static function version(int $schoolId): int
    {
        return (int) Cache::get(self::versionKey($schoolId), 1);
    }

    private static function versionKey(int $schoolId): string
    {
        return "school:{$schoolId}:version";
    }
}
