<?php

namespace App\Providers\Modules;

use App\Models\Promotion\PromotionRun;
use App\Models\Students\StudentEnrollment;
use App\Services\Promotion\EnrollmentUsageInspector;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\ServiceProvider;

/**
 * Year-end promotion / rollover (master plan Phase 2B; docs/promotion/ROLLOVER_RULES.md).
 *
 * EnrollmentUsageInspector is a singleton so other modules can register extra "is this enrollment used?" checks
 * from their own providers: app(EnrollmentUsageInspector::class)->extend('label', fn (StudentEnrollment $e) => ...).
 */
class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnrollmentUsageInspector::class);
    }

    public function boot(): void
    {
        StudentEnrollment::resolveRelationUsing(
            'promotionRun',
            fn (StudentEnrollment $enrollment): BelongsTo => $enrollment->belongsTo(PromotionRun::class, 'promotion_run_id'),
        );
    }
}
