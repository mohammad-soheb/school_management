<?php

// Core providers plus every app/Providers/Modules/*ServiceProvider.php, so a module can register
// bindings, listeners or view composers by adding a file instead of editing this one.
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    ...array_map(
        fn (string $file): string => 'App\\Providers\\Modules\\'.basename($file, '.php'),
        glob(__DIR__.'/../app/Providers/Modules/*ServiceProvider.php') ?: [],
    ),
];
