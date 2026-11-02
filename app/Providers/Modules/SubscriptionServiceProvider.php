<?php

namespace App\Providers\Modules;

use App\Contracts\SubscriptionAccessPolicy;
use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use App\Services\Platform\Subscriptions\SubscriptionAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Platform subscriptions & renewals (master plan Phase 8, FR-801 .. FR-809).
 *  - replaces the pass-through SubscriptionAccessPolicy with the real expiry policy (FR-807);
 *  - adds School::subscription() without editing the core School model;
 *  - schedules the daily status refresh (01:00 school time).
 */
class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registered after AppServiceProvider (bootstrap/providers.php), so this binding wins.
        $this->app->bind(SubscriptionAccessPolicy::class, SubscriptionAccess::class);
    }

    public function boot(): void
    {
        School::resolveRelationUsing('subscription', fn (School $school) => $school->hasOne(SchoolSubscription::class));

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('erp:subscriptions:refresh')
                ->dailyAt('01:00')
                ->timezone(config('app.display_timezone'))
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
