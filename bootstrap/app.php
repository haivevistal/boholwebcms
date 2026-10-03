<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminAccess::class,
            'cap' => \App\Http\Middleware\RequireCapability::class,
        ]);

        // The AJAX endpoint (like admin-ajax.php) keeps CSRF protection.
        // Plugins that need third-party callbacks (payment webhooks, etc.)
        // should register their routes under /cms-webhook/* which is exempt.
        $middleware->validateCsrfTokens(except: ['cms-webhook/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
