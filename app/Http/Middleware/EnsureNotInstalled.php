<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the web installer once BoholwebCMS is installed.
 * To reinstall, delete storage/app/.cms-installed (and the database tables).
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (cms_installed()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'BoholwebCMS is already installed.'], 403)
                : redirect('/admin');
        }

        return $next($request);
    }
}
