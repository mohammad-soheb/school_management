<?php

namespace Tests\Feature\Ui\Concerns;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Helpers for the UI tests: render Blade snippets with a session (old input, flash messages) and load the
 * local-only dev routes (bootstrap/app.php registers them only when APP_ENV=local, tests run as "testing").
 */
trait RendersComponents
{
    /** Give the current request a session so old() / session() work while rendering a snippet. */
    protected function withRequestSession(): void
    {
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    /** Pretend the last submit failed validation: flash the input and share the errors with every view. */
    protected function withFailedSubmit(array $input, array $errors): void
    {
        $this->withRequestSession();
        $this->app['session.store']->flashInput($input);
        // Move the flashed input from "new" to "old", as the next request would.
        $this->app['session.store']->ageFlashData();

        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag($errors));
        view()->share('errors', $bag);
    }

    protected function loadDevRoutes(): void
    {
        Route::middleware('web')->group(base_path('routes/dev.php'));
        $routes = $this->app['router']->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();
    }
}
