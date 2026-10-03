<?php

namespace App\Http\Middleware;

use App\Cms\Support\Assets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /admin. Everyone with the "read" capability may enter (subscribers
 * see their profile); "admin_init" fires for every admin request.
 */
class AdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! cms_installed()) {
            return redirect()->route('install.notice');
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! current_user_can('read')) {
            abort(403, 'Sorry, you are not allowed to access this page.');
        }

        app(Assets::class)->setContext('admin');

        do_action('admin_init', $request);

        return $next($request);
    }
}
