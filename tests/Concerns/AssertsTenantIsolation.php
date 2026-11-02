<?php

namespace Tests\Concerns;

use App\Models\Platform\School;
use App\Models\User;

/**
 * Shared "cross-school attack" assertions. Every module that adds tenant data must prove another school's user
 * cannot reach it by guessing ids. Typical use inside a feature test:
 *
 *   $this->assertUnreachableFromOtherSchool([
 *       ['GET',    route('school.students.show', $student)],
 *       ['PUT',    route('school.students.update', $student), ['first_name' => 'Hacked']],
 *       ['DELETE', route('school.students.destroy', $student)],
 *   ]);
 *
 * The intruder is a School Admin of a different school (the most privileged non-platform role), so a pass means the
 * block is the tenant boundary and not merely a missing permission.
 */
trait AssertsTenantIsolation
{
    /**
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $requests  [method, url, data]
     */
    protected function assertUnreachableFromOtherSchool(array $requests, ?School $intruderSchool = null, ?User $intruder = null): void
    {
        $intruderSchool ??= $this->makeSchool();
        $intruder ??= $this->makeMember($intruderSchool, 'school_admin');

        $this->signInTo($intruderSchool, $intruder);

        foreach ($requests as $request) {
            [$method, $url] = $request;
            $response = $this->call($method, $url, $request[2] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [403, 404],
                "{$method} {$url} should be invisible to another school but returned HTTP {$response->getStatusCode()}.",
            );
        }
    }
}
