<?php

namespace App\Providers;

use App\Contracts\SubscriptionAccessPolicy;
use App\Listeners\RecordAuthEvents;
use App\Models\User;
use App\Support\Database\TenantBlueprint;
use App\Support\Identity\Permissions;
use App\Support\Money\Money;
use App\Support\Subscriptions\AllowAllSubscriptionAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One TenantContext object per process whose STATE is cleared at every request and queued job boundary
        // (ResetTenantContext middleware + queue hooks below). A single instance means every service holding a
        // reference sees the same school, even when controllers are reused (Octane, tests).
        $this->app->singleton(TenantContext::class);

        // Pass-through until the subscriptions module binds a real policy (see EnsureSubscriptionAccess).
        $this->app->bind(SubscriptionAccessPolicy::class, AllowAllSubscriptionAccess::class);

        $this->app->scoped(\App\Support\Identity\AssignmentScope::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TenantBlueprint::register();

        Queue::before(fn () => $this->app->make(TenantContext::class)->clear());
        Queue::after(fn () => $this->app->make(TenantContext::class)->clear());
        Queue::failing(fn () => $this->app->make(TenantContext::class)->clear());

        // Catch N+1 queries, silently discarded attributes and typos in attribute names during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        $this->defineAbilities();
        $this->defineRateLimiters();
        $this->listenForAuthEvents();

        // @money($paise) prints an integer paise amount as ₹1,23,456.50
        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money\\Money::format((int) ({$expression}))); ?>");
    }

    /**
     * Every permission in config/permissions/* becomes a Gate ability, so $user->can('fees.payments.post'),
     * @can('students.create') and Gate::allows(...) all work. School permissions come from the active membership;
     * platform abilities are granted to the Super Admin only.
     */
    private function defineAbilities(): void
    {
        foreach (Permissions::allSchool() as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->hasPermission($ability));
        }

        foreach (array_keys(Permissions::platform()) as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->isPlatformAdmin());
        }
    }

    private function listenForAuthEvents(): void
    {
        Event::listen(Login::class, [RecordAuthEvents::class, 'login']);
        Event::listen(Failed::class, [RecordAuthEvents::class, 'failed']);
        Event::listen(TwoFactorAuthenticationEnabled::class, [RecordAuthEvents::class, 'twoFactorEnabled']);
        Event::listen(TwoFactorAuthenticationConfirmed::class, [RecordAuthEvents::class, 'twoFactorConfirmed']);
        Event::listen(TwoFactorAuthenticationDisabled::class, [RecordAuthEvents::class, 'twoFactorDisabled']);
        Event::listen(TwoFactorAuthenticationFailed::class, [RecordAuthEvents::class, 'twoFactorFailed']);
        Event::listen(RecoveryCodesGenerated::class, [RecordAuthEvents::class, 'recoveryCodesGenerated']);
        Event::listen(RecoveryCodeReplaced::class, [RecordAuthEvents::class, 'recoveryCodeUsed']);
    }

    private function defineRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Accepting an invitation is unauthenticated and keyed by a secret token: slow down guessing.
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // File downloads and exports are per user, so one account cannot scrape a school's documents.
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getKey() ?? $request->ip())));
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getKey() ?? $request->ip())));
    }
}
