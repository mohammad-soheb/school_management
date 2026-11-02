<?php

namespace App\Support\Tenancy;

use App\Models\Identity\SchoolMembership;
use App\Models\Platform\School;
use App\Models\SchoolSetup\AcademicYear;
use App\Support\Tenancy\Exceptions\TenantContextMissing;
use Closure;

/**
 * The school (tenant) the current request or queued job is acting for.
 *
 * Bound as a singleton whose state is cleared at the start and end of every request (ResetTenantContext) and
 * every queued job (queue hooks in AppServiceProvider).
 * Only ResolveSchoolContext (from a server-validated membership), TenantAwareJob and the explicit
 * run()/withoutTenant() helpers may change it. Never set it from a browser-supplied school id.
 */
final class TenantContext
{
    private ?School $school = null;

    private ?SchoolMembership $membership = null;

    private int $bypassDepth = 0;

    /** @var list<string>|null */
    private ?array $permissions = null;

    private ?AcademicYear $academicYear = null;

    private bool $academicYearLoaded = false;

    public function set(School $school, ?SchoolMembership $membership = null): void
    {
        $this->school = $school;
        $this->membership = $membership;
        $this->forgetMemoized();
    }

    /** Back to "no school", including any withoutTenant() bypass. Called at every request and job boundary. */
    public function clear(): void
    {
        $this->school = null;
        $this->membership = null;
        $this->bypassDepth = 0;
        $this->forgetMemoized();
    }

    public function has(): bool
    {
        return $this->school !== null;
    }

    public function id(): ?int
    {
        return $this->school?->getKey();
    }

    /** @throws TenantContextMissing */
    public function requireId(): int
    {
        return $this->id() ?? throw new TenantContextMissing;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    /** @throws TenantContextMissing */
    public function requireSchool(): School
    {
        return $this->school ?? throw new TenantContextMissing;
    }

    public function membership(): ?SchoolMembership
    {
        return $this->membership;
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Run $callback as $school, then restore whatever context was active before (used by jobs, seeders, console).
     */
    public function run(School $school, Closure $callback, ?SchoolMembership $membership = null): mixed
    {
        $previous = [$this->school, $this->membership];
        $this->set($school, $membership);

        try {
            return $callback();
        } finally {
            [$this->school, $this->membership] = $previous;
            $this->forgetMemoized();
        }
    }

    /**
     * Explicit, greppable escape hatch: run without the school filter. Only for platform-level work
     * (Super Admin backoffice, membership lookup while resolving a request, console tooling). Never in school-facing code.
     */
    public function withoutTenant(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    /**
     * Permission names granted to the current membership (union of its roles), memoised for the request.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->membership === null) {
            return [];
        }

        return $this->permissions ??= $this->membership->permissionNames();
    }

    /** The school's current academic year, or null if none is set yet. */
    public function academicYear(): ?AcademicYear
    {
        if (! $this->has()) {
            return null;
        }

        if (! $this->academicYearLoaded) {
            $this->academicYear = AcademicYear::query()->where('is_current', true)->first();
            $this->academicYearLoaded = true;
        }

        return $this->academicYear;
    }

    public function academicYearLabel(): ?string
    {
        return $this->academicYear()?->name;
    }

    /** Drop memoised data after roles, memberships or the current academic year change. */
    public function forgetMemoized(): void
    {
        $this->permissions = null;
        $this->academicYear = null;
        $this->academicYearLoaded = false;
    }
}
