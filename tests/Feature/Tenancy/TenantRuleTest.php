<?php

namespace Tests\Feature\Tenancy;

use App\Models\SchoolSetup\SchoolClass;
use App\Models\Students\Student;
use App\Support\Tenancy\TenantRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TenantRuleTest extends TestCase
{
    public function test_exists_rejects_an_id_from_another_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $mine = $this->inSchool($a, fn () => SchoolClass::factory()->create());
        $theirs = $this->inSchool($b, fn () => SchoolClass::factory()->create());

        $this->inSchool($a, function () use ($mine, $theirs) {
            $rules = ['school_class_id' => [TenantRule::exists('school_classes')]];

            $this->assertTrue(Validator::make(['school_class_id' => $mine->id], $rules)->passes());
            $this->assertTrue(Validator::make(['school_class_id' => $theirs->id], $rules)->fails());
        });
    }

    public function test_unique_only_considers_the_active_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $this->inSchool($b, fn () => Student::factory()->create(['admission_no' => '777']));
        $this->inSchool($a, fn () => Student::factory()->create(['admission_no' => '888']));

        $this->inSchool($a, function () {
            $rules = ['admission_no' => [TenantRule::unique('students', 'admission_no')]];

            $this->assertTrue(Validator::make(['admission_no' => '777'], $rules)->passes(), 'Another school already uses 777; that must not block school A.');
            $this->assertTrue(Validator::make(['admission_no' => '888'], $rules)->fails());
        });
    }

    public function test_rules_refuse_to_build_without_a_school_context(): void
    {
        $this->expectException(\App\Support\Tenancy\Exceptions\TenantContextMissing::class);

        TenantRule::exists('school_classes');
    }
}
