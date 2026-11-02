<?php

namespace App\Providers\Modules;

use App\Models\Communication\Homework;
use App\Models\User;
use App\Services\Communication\HomeworkAccess;
use App\Services\Communication\NoticeAudienceResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Homework & notices module wiring (FR-505 - FR-508).
 */
class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-request memo of teaching assignments / audiences: scoped, so it never leaks between requests or jobs.
        $this->app->scoped(HomeworkAccess::class);
        $this->app->scoped(NoticeAudienceResolver::class);
    }

    public function boot(): void
    {
        // Derived abilities for routes and the menu: "holds any homework permission" / "may set homework".
        Gate::define('homework.access', fn (User $user): bool => $user->can('viewAny', Homework::class));
        Gate::define('homework.author', fn (User $user): bool => app(HomeworkAccess::class)->canAuthor($user));
        Gate::define('notices.access', fn (User $user): bool => $user->hasPermission('notices.view') || $user->hasPermission('notices.manage'));
    }
}
