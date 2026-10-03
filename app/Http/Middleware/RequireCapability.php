<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware:  ->middleware('cap:manage_options')
 * Several capabilities separated by "|" mean "any of".
 */
class RequireCapability
{
    public function handle(Request $request, Closure $next, string $capabilities): Response
    {
        foreach (explode('|', $capabilities) as $cap) {
            if (current_user_can($cap)) {
                return $next($request);
            }
        }

        abort(403, 'Sorry, you are not allowed to access this page.');
    }
}
