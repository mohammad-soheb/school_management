<?php

namespace App\Support\Tenancy;

use App\Models\Identity\SchoolMembership;
use App\Models\Platform\School;
use App\Models\User;
use App\Support\Tenancy\Exceptions\TenantContextMissing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Base class for queued work on behalf of one school (imports, exports, bulk PDFs).
 *
 * The payload carries only ids: the school, the acting user and a purpose label. When the job runs, handle()
 * restores the tenant context and re-checks that the actor is still an active member (and holds
 * requiredPermission()) BEFORE process() touches any data. Subclasses implement process() only.
 *
 *   dispatch(new ExportStudentsJob($school->id, $user->id, 'students.export'));
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $schoolId,
        public readonly ?int $actorId,
        public readonly string $purpose,
    ) {}

    final public function handle(TenantContext $context): void
    {
        $school = School::query()->find($this->schoolId)
            ?? throw new TenantContextMissing("School {$this->schoolId} no longer exists.");

        $actor = null;
        $membership = null;

        if ($this->actorId !== null) {
            $actor = User::query()->find($this->actorId);
            $membership = $actor === null ? null : $context->withoutTenant(
                fn () => SchoolMembership::query()
                    ->where('school_id', $school->id)
                    ->where('user_id', $actor->id)
                    ->where('status', 'active')
                    ->first(),
            );

            if ($actor === null || ! $actor->isActive() || $membership === null) {
                throw new AccessDeniedHttpException("User {$this->actorId} may no longer act for school {$this->schoolId}.");
            }
        }

        $context->run($school, function () use ($actor): void {
            $permission = $this->requiredPermission();

            if ($permission !== null && ($actor === null || ! Gate::forUser($actor)->allows($permission))) {
                throw new AccessDeniedHttpException("Missing permission {$permission} for job {$this->purpose}.");
            }

            $this->process($actor);
        }, $membership);
    }

    /** Permission the actor must still hold when the job runs (null = membership alone is enough). */
    protected function requiredPermission(): ?string
    {
        return null;
    }

    abstract protected function process(?User $actor): void;
}
