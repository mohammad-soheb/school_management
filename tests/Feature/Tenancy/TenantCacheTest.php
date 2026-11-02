<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\Exceptions\TenantContextMissing;
use App\Support\Tenancy\TenantCache;
use Tests\TestCase;

class TenantCacheTest extends TestCase
{
    public function test_two_schools_never_share_a_cache_entry_even_with_the_same_key(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $fromA = $this->inSchool($a, fn () => TenantCache::remember('dashboard', 60, fn () => 'school A numbers'));
        $fromB = $this->inSchool($b, fn () => TenantCache::remember('dashboard', 60, fn () => 'school B numbers'));

        $this->assertSame('school A numbers', $fromA);
        $this->assertSame('school B numbers', $fromB);
        $this->assertSame('school A numbers', $this->inSchool($a, fn () => TenantCache::remember('dashboard', 60, fn () => 'recomputed')));
    }

    public function test_flush_invalidates_only_that_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->inSchool($a, fn () => TenantCache::remember('k', 60, fn () => 'a1'));
        $this->inSchool($b, fn () => TenantCache::remember('k', 60, fn () => 'b1'));

        $this->inSchool($a, fn () => TenantCache::flush());

        $this->assertSame('a2', $this->inSchool($a, fn () => TenantCache::remember('k', 60, fn () => 'a2')));
        $this->assertSame('b1', $this->inSchool($b, fn () => TenantCache::remember('k', 60, fn () => 'b2')));
    }

    public function test_keys_cannot_be_built_without_a_school(): void
    {
        $this->expectException(TenantContextMissing::class);

        TenantCache::key('anything');
    }
}
