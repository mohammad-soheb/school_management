<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\Subscriptions\RenewalEventType;
use App\Enums\Subscriptions\SubscriptionState;
use App\Models\Platform\RenewalEvent;
use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\SubscriptionPlan;
use App\Services\Platform\Subscriptions\RenewalService;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/** Record a verified payment and renew (FR-804, §10.5), assign / change / suspend (FR-803, FR-808). */
class RenewalTest extends TestCase
{
    private function subscribe(School $school, int $expiresInDays, array $attributes = []): SchoolSubscription
    {
        $plan = SubscriptionPlan::factory()->create(['monthly_price_paise' => 149900]);

        return SchoolSubscription::factory()->expiringInDays($expiresInDays)->create(['school_id' => $school->id, 'subscription_plan_id' => $plan->id] + $attributes);
    }

    /** @return array<string, mixed> */
    private function payment(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '1499',
            'paid_on' => school_today()->toDateString(),
            'mode' => 'upi',
            'reference' => 'UTR123456789',
            'months' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function test_early_renewal_extends_from_the_current_expiry(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $subscription = $this->subscribe($school, 10);
        $oldExpiry = $subscription->expires_on;

        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $school), $this->payment())
            ->assertRedirect(route('platform.schools.show', $school))->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame($oldExpiry->addMonthNoOverflow()->toDateString(), $subscription->expires_on->toDateString());
        $this->assertSame(SubscriptionState::Active, $subscription->status()->state);

        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame($school->id, $payment->school_id);
        $this->assertSame(149900, $payment->amount_paise);
        $this->assertSame(149900, $payment->expected_paise);
        $this->assertSame($oldExpiry->addDay()->toDateString(), $payment->period_starts_on->toDateString());
        $this->assertSame($super->id, $payment->recorded_by);

        $event = RenewalEvent::query()->sole();
        $this->assertSame(RenewalEventType::Renewed, $event->type);
        $this->assertSame($payment->id, $event->subscription_payment_id);
        $this->assertSame($oldExpiry->toDateString(), $event->previous_expires_on->toDateString());

        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.renewed', 'school_id' => $school->id, 'actor_id' => $super->id]);
    }

    public function test_renewal_after_expiry_starts_today_and_restores_access(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $subscription = $this->subscribe($school, -30);
        $this->assertSame(SubscriptionState::Expired, $subscription->status()->state);

        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $school), $this->payment(['months' => 3, 'amount' => '4497']))
            ->assertRedirect();

        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(school_today()->toDateString(), $payment->period_starts_on->toDateString());
        $this->assertSame(3, $payment->months);
        $this->assertSame(SubscriptionState::Active, $subscription->fresh()->status()->state);
    }

    public function test_renewal_extends_only_the_right_school(): void
    {
        $super = $this->makePlatformAdmin();
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $subA = $this->subscribe($a, 5);
        $subB = $this->subscribe($b, 5);
        $before = $subB->expires_on->toDateString();

        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $a), $this->payment())->assertRedirect();

        $this->assertSame($before, $subB->fresh()->expires_on->toDateString());
        $this->assertNotSame($before, $subA->fresh()->expires_on->toDateString());
        $this->assertDatabaseMissing('subscription_payments', ['school_id' => $b->id]);
    }

    public function test_a_double_submit_records_one_payment_and_extends_once(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $subscription = $this->subscribe($school, 10);
        $data = $this->payment();

        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $school), $data)->assertSessionHas('status');
        $expiryAfterFirst = $subscription->fresh()->expires_on->toDateString();

        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $school), $data)->assertSessionHas('info');

        $this->assertSame(1, SubscriptionPayment::query()->count());
        $this->assertSame(1, RenewalEvent::query()->count());
        $this->assertSame($expiryAfterFirst, $subscription->fresh()->expires_on->toDateString());
    }

    public function test_service_returns_the_first_payment_for_a_repeated_key_and_rejects_reuse_for_another_school(): void
    {
        $super = $this->makePlatformAdmin();
        $subA = $this->subscribe($this->makeSchool(), 10);
        $subB = $this->subscribe($this->makeSchool(), 10);
        $data = ['amount_paise' => 149900, 'paid_on' => school_today()->toDateString(), 'mode' => 'cash', 'months' => 1, 'idempotency_key' => 'key-1'];
        $service = app(RenewalService::class);

        $first = $service->recordPayment($subA, $data, $super);
        $again = $service->recordPayment($subA, $data, $super);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($first->id, $again->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->recordPayment($subB, $data, $super);
    }

    public function test_payment_validation(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $this->subscribe($school, 10);
        $url = route('platform.subscriptions.schools.payments.store', $school);

        $this->actingAs($super)->post($url, $this->payment(['amount' => '0']))->assertSessionHasErrors('amount');
        $this->actingAs($super)->post($url, $this->payment(['amount' => '12.345']))->assertSessionHasErrors('amount');
        $this->actingAs($super)->post($url, $this->payment(['reference' => '']))->assertSessionHasErrors('reference');
        $this->actingAs($super)->post($url, $this->payment(['paid_on' => school_today()->addDay()->toDateString()]))->assertSessionHasErrors('paid_on');
        $this->actingAs($super)->post($url, $this->payment(['mode' => 'bitcoin']))->assertSessionHasErrors('mode');
        $this->actingAs($super)->post($url, $this->payment(['months' => 0]))->assertSessionHasErrors('months');
        $this->actingAs($super)->post($url, $this->payment(['months' => 37]))->assertSessionHasErrors('months');
        $this->actingAs($super)->post($url, $this->payment(['idempotency_key' => '']))->assertSessionHasErrors('idempotency_key');

        // Cash needs no reference; a short payment is accepted and stays visible against the expected amount.
        $this->actingAs($super)->post($url, $this->payment(['mode' => 'cash', 'reference' => '', 'amount' => '1,000']))->assertSessionHasNoErrors();
        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(-49900, $payment->differencePaise());
    }

    public function test_payments_and_history_are_immutable(): void
    {
        $super = $this->makePlatformAdmin();
        $subscription = $this->subscribe($this->makeSchool(), 10);
        $payment = app(RenewalService::class)->recordPayment($subscription, [
            'amount_paise' => 149900, 'paid_on' => school_today()->toDateString(), 'mode' => 'cash', 'months' => 1, 'idempotency_key' => 'imm-1',
        ], $super);

        try {
            $payment->update(['amount_paise' => 1]);
            $this->fail('A payment was updated.');
        } catch (LogicException) {
        }

        try {
            RenewalEvent::query()->firstOrFail()->delete();
            $this->fail('A history line was deleted.');
        } catch (LogicException) {
        }

        $this->assertSame(149900, $payment->fresh()->amount_paise);
        $this->assertSame(1, RenewalEvent::query()->count());
    }

    public function test_assigning_a_plan_activates_the_school(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $plan = SubscriptionPlan::factory()->create(['billing_period_months' => 1]);
        $retired = SubscriptionPlan::factory()->inactive()->create();
        $url = route('platform.subscriptions.schools.store', $school);

        $this->actingAs($super)->post($url, ['subscription_plan_id' => $retired->id, 'starts_on' => '2026-11-01', 'grace_days' => 7])
            ->assertSessionHasErrors('subscription_plan_id');
        $this->actingAs($super)->post($url, ['subscription_plan_id' => $plan->id, 'starts_on' => '2026-11-01', 'expires_on' => '2026-10-01', 'grace_days' => 7])
            ->assertSessionHasErrors('expires_on');
        $this->actingAs($super)->post($url, ['subscription_plan_id' => $plan->id, 'starts_on' => '2026-11-01', 'grace_days' => 500])
            ->assertSessionHasErrors('grace_days');

        $this->actingAs($super)->post($url, ['subscription_plan_id' => $plan->id, 'starts_on' => '2026-11-01', 'grace_days' => 5])
            ->assertRedirect(route('platform.schools.show', $school));

        $subscription = SchoolSubscription::query()->where('school_id', $school->id)->sole();
        $this->assertSame('2026-11-30', $subscription->expires_on->toDateString());
        $this->assertSame(5, $subscription->grace_days);
        $this->assertSame(RenewalEventType::Activated, RenewalEvent::query()->sole()->type);
        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.activated', 'school_id' => $school->id]);

        // A second assignment is refused; the form redirects to the edit page.
        $this->actingAs($super)->post($url, ['subscription_plan_id' => $plan->id, 'starts_on' => '2026-11-01', 'grace_days' => 5])
            ->assertSessionHasErrors('subscription_plan_id');
        $this->actingAs($super)->get(route('platform.subscriptions.schools.create', $school))
            ->assertRedirect(route('platform.subscriptions.schools.edit', $school));
        $this->assertSame(1, SchoolSubscription::query()->count());
    }

    public function test_changing_plan_and_dates_is_recorded_and_audited(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $subscription = $this->subscribe($school, 30);
        $growth = SubscriptionPlan::factory()->create(['name' => 'Growth']);

        $this->actingAs($super)->put(route('platform.subscriptions.schools.update', $school), [
            'subscription_plan_id' => $growth->id,
            'starts_on' => $subscription->starts_on->toDateString(),
            'expires_on' => $subscription->expires_on->addDays(5)->toDateString(),
            'grace_days' => 10,
            'reason' => 'Upgraded after call',
        ])->assertRedirect(route('platform.schools.show', $school));

        $subscription->refresh();
        $this->assertSame($growth->id, $subscription->subscription_plan_id);
        $this->assertSame(10, $subscription->grace_days);
        $this->assertEqualsCanonicalizing(
            [RenewalEventType::PlanChanged, RenewalEventType::Adjusted],
            RenewalEvent::query()->get()->pluck('type')->all(),
        );
        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.plan_changed', 'school_id' => $school->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.adjusted', 'school_id' => $school->id]);

        $this->actingAs($super)->put(route('platform.subscriptions.schools.update', $school), ['subscription_plan_id' => $growth->id])
            ->assertSessionHasErrors(['starts_on', 'expires_on', 'grace_days', 'reason']);
    }

    public function test_suspend_and_resume(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();
        $subscription = $this->subscribe($school, 30);

        $this->actingAs($super)->post(route('platform.subscriptions.schools.suspend', $school), [])->assertSessionHasErrors('suspension_reason');
        $this->actingAs($super)->post(route('platform.subscriptions.schools.suspend', $school), ['suspension_reason' => 'Cheque bounced'])->assertRedirect();

        $this->assertSame(SubscriptionState::Suspended, $subscription->fresh()->status()->state);
        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.suspended', 'school_id' => $school->id]);

        $this->actingAs($super)->post(route('platform.subscriptions.schools.resume', $school))->assertRedirect();
        $this->assertSame(SubscriptionState::Active, $subscription->fresh()->status()->state);
        $this->assertDatabaseHas('audit_events', ['event' => 'subscription.resumed', 'school_id' => $school->id]);
        $this->assertSame(2, RenewalEvent::query()->count());

        $this->actingAs($super)->post(route('platform.subscriptions.schools.resume', $school))->assertSessionHasErrors('subscription');
    }

    public function test_routes_for_a_school_without_subscription_are_404(): void
    {
        $super = $this->makePlatformAdmin();
        $school = $this->makeSchool();

        $this->actingAs($super)->get(route('platform.subscriptions.schools.payments.create', $school))->assertNotFound();
        $this->actingAs($super)->post(route('platform.subscriptions.schools.payments.store', $school), $this->payment())->assertNotFound();
        $this->actingAs($super)->get(route('platform.subscriptions.schools.edit', $school))->assertNotFound();
    }
}
