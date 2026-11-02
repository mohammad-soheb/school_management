<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Three areas, each with its own middleware stack (see plan.md §2.4).
            // Every area file auto-loads routes/<area>/*.php so a module only adds files, never edits shared ones.
            Route::middleware(['web', 'auth', 'active.user', 'platform.admin', 'two-factor'])
                ->prefix('platform')->name('platform.')
                ->group(base_path('routes/platform.php'));

            Route::middleware(['web', 'auth', 'active.user', 'school.context', 'school.staff', 'subscription.access', 'two-factor.role'])
                ->prefix('app')->name('school.')
                ->group(base_path('routes/school.php'));

            Route::middleware(['web', 'auth', 'active.user', 'school.context', 'guardian', 'subscription.access'])
                ->prefix('parent')->name('parent.')
                ->group(base_path('routes/parent.php'));

            if (app()->environment('local')) {
                Route::middleware('web')->group(base_path('routes/dev.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'school.context' => \App\Http\Middleware\ResolveSchoolContext::class,
            'school.staff' => \App\Http\Middleware\EnsureSchoolStaff::class,
            'two-factor' => \App\Http\Middleware\RequireTwoFactor::class,
            'two-factor.role' => \App\Http\Middleware\RequireTwoFactorForRole::class,
            'subscription.access' => \App\Http\Middleware\EnsureSubscriptionAccess::class,
            'guardian' => \App\Http\Middleware\EnsureGuardianAccount::class,
        ]);

        $middleware->prepend(\App\Http\Middleware\ResetTenantContext::class);

        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // The school must be resolved BEFORE route-model binding, otherwise {student} etc. would be looked up with no
        // tenant (and fail closed). Guardian/staff gates follow so their 403s also win over binding.
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\ResolveSchoolContext::class);
        $middleware->appendToPriorityList(\App\Http\Middleware\ResolveSchoolContext::class, \App\Http\Middleware\EnsureSchoolStaff::class);
        $middleware->appendToPriorityList(\App\Http\Middleware\ResolveSchoolContext::class, \App\Http\Middleware\EnsureGuardianAccount::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
