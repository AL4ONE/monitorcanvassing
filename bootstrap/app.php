<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable CORS globally for ALL routes (web + api)
        $middleware->prepend(\App\Http\Middleware\Cors::class);

        // Also add to API routes for redundancy
        $middleware->api(prepend: [
            \App\Http\Middleware\Cors::class,
        ]);

        // Exclude /login and /register from CSRF protection (API endpoints)
        $middleware->validateCsrfTokens(except: [
            '/login',
            '/register',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
