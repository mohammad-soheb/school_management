<?php

namespace Tests\Feature\Tenancy;

use App\Models\Students\Student;
use App\Models\User;
use App\Support\Tenancy\TenantAwareJob;
use App\Support\Tenancy\TenantContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class TenantAwareJobTest extends TestCase
{
    public function test_a_job_runs_inside_the_school_it_was_dispatched_for(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $admin = $this->makeMember($a, 'school_admin');
        $this->inSchool($a, fn () => Student::factory()->count(2)->create());
        $this->inSchool($b, fn () => Student::factory()->count(5)->create());

        CountStudentsJob::$seen = null;
        CountStudentsJob::dispatchSync($a->id, $admin->id, 'count');

        $this->assertSame(2, CountStudentsJob::$seen, 'The job must only see school A students.');
        $this->assertNull($this->tenant()->id(), 'The context must be restored after the job.');
    }

    public function test_a_job_is_refused_if_the_actor_is_no_longer_a_member(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $memberOfB = $this->makeMember($b, 'school_admin');

        $this->expectException(AccessDeniedHttpException::class);

        CountStudentsJob::dispatchSync($a->id, $memberOfB->id, 'count');
    }

    public function test_a_job_is_refused_if_the_actor_lost_the_required_permission(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeMember($school, 'teacher'); // teachers cannot export students

        $this->expectException(AccessDeniedHttpException::class);

        ExportStudentsJob::dispatchSync($school->id, $teacher->id, 'export');
    }

    public function test_a_job_with_the_required_permission_runs(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        ExportStudentsJob::$ran = false;
        ExportStudentsJob::dispatchSync($school->id, $admin->id, 'export');

        $this->assertTrue(ExportStudentsJob::$ran);
    }
}

class CountStudentsJob extends TenantAwareJob
{
    public static ?int $seen = null;

    protected function process(?User $actor): void
    {
        self::$seen = Student::query()->count();
        assert(app(TenantContext::class)->id() === $this->schoolId);
    }
}

class ExportStudentsJob extends TenantAwareJob
{
    public static bool $ran = false;

    protected function requiredPermission(): ?string
    {
        return 'students.export';
    }

    protected function process(?User $actor): void
    {
        self::$ran = true;
    }
}
