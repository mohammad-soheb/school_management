<?php

namespace Tests\Feature\Tenancy;

use App\Models\Students\Student;
use App\Support\Tenancy\Exceptions\CrossTenantWrite;
use App\Support\Tenancy\Exceptions\TenantContextMissing;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

/**
 * Tenant isolation at the model layer (plan.md §2.6, levels 2-3). Two schools deliberately share the same
 * admission numbers so any leak shows up as a wrong row, not just a wrong count.
 */
class SchoolScopeTest extends TestCase
{
    public function test_tenant_queries_fail_closed_without_a_school_context(): void
    {
        $this->expectException(TenantContextMissing::class);

        Student::query()->count();
    }

    public function test_queries_only_see_the_active_schools_rows(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $studentA = $this->inSchool($a, fn () => Student::factory()->create(['admission_no' => '1001']));
        $studentB = $this->inSchool($b, fn () => Student::factory()->create(['admission_no' => '1001']));

        $this->inSchool($a, function () use ($studentA, $studentB) {
            $this->assertSame([$studentA->id], Student::query()->pluck('id')->all());
            $this->assertNull(Student::query()->find($studentB->id), 'School A must not find a school B row by id.');
        });

        $this->inSchool($b, function () use ($studentB) {
            $this->assertSame([$studentB->id], Student::query()->pluck('id')->all());
        });
    }

    public function test_route_style_lookup_of_another_schools_id_is_a_not_found(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $foreign = $this->inSchool($b, fn () => Student::factory()->create());

        $this->inSchool($a, function () use ($foreign) {
            $this->expectException(ModelNotFoundException::class);

            // This is what implicit route-model binding does.
            (new Student)->resolveRouteBinding($foreign->id) ?? throw new ModelNotFoundException;
        });
    }

    public function test_without_tenant_is_the_only_way_to_see_every_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $this->inSchool($a, fn () => Student::factory()->count(2)->create());
        $this->inSchool($b, fn () => Student::factory()->count(3)->create());

        $this->assertSame(5, $this->tenant()->withoutTenant(fn () => Student::query()->count()));
    }

    public function test_school_id_is_filled_from_the_active_school_on_create(): void
    {
        $school = $this->makeSchool();

        $student = $this->inSchool($school, fn () => Student::create([
            'admission_no' => 'A-1', 'first_name' => 'Asha', 'gender' => 'female', 'date_of_birth' => '2015-01-01',
        ]));

        $this->assertSame($school->id, $student->school_id);
    }

    public function test_creating_a_row_for_another_school_while_acting_for_one_is_refused(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->expectException(CrossTenantWrite::class);

        $this->inSchool($a, fn () => Student::factory()->create(['school_id' => $b->id]));
    }

    public function test_school_id_cannot_be_mass_assigned(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->expectException(MassAssignmentException::class);

        $this->inSchool($a, fn () => Student::create([
            'school_id' => $b->id,
            'admission_no' => 'EVIL', 'first_name' => 'Eve', 'gender' => 'female', 'date_of_birth' => '2015-01-01',
        ]));
    }

    public function test_a_row_cannot_be_moved_to_another_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $student = $this->inSchool($a, fn () => Student::factory()->create());

        $this->expectException(CrossTenantWrite::class);

        $this->inSchool($a, function () use ($student, $b) {
            $student->school_id = $b->id;
            $student->save();
        });
    }

    public function test_run_restores_the_previous_context(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->actAsTenant($a);
        $this->inSchool($b, fn () => $this->assertSame($b->id, $this->tenant()->id()));

        $this->assertSame($a->id, $this->tenant()->id());
    }

    public function test_same_admission_number_is_allowed_in_two_schools_but_not_twice_in_one(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->inSchool($a, fn () => Student::factory()->create(['admission_no' => 'DUP']));
        $this->inSchool($b, fn () => Student::factory()->create(['admission_no' => 'DUP']));
        $this->assertDatabaseCount('students', 2);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->inSchool($a, fn () => Student::factory()->create(['admission_no' => 'DUP']));
    }
}
