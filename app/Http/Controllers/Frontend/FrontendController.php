<?php

namespace App\Http\Controllers\Frontend;

use App\Cms\Frontend\RequestResolver;
use App\Cms\Frontend\ThemeData;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Renders every front-end URL through the active theme.
 *
 * The server resolves the URL (permalinks → query → template hierarchy),
 * runs content filters/shortcodes, and hands the theme a single
 * "Front/Theme" Inertia page. The theme's React bundle picks the first
 * template in `view.templates` that it implements.
 */
class FrontendController extends Controller
{
    public function show(Request $request, RequestResolver $resolver, ThemeData $themeData, ?string $path = null)
    {
        if (! cms_installed()) {
            return redirect()->route('install.notice');
        }

        do_action('template_redirect', $request, $path);

        $context = $resolver->resolve($request, (string) $path);

        if ($context['redirect']) {
            return redirect($context['redirect'], 301);
        }

        $props = $themeData->build($request, $context);
        $props['meta'] = $themeData->meta($context, $props);

        $response = Inertia::render('Front/Theme', $props)
            ->withViewData(['meta' => $props['meta']]);

        $httpResponse = $response->toResponse($request);

        // Real status codes for crawlers / full page loads; Inertia XHR visits
        // stay 200 so the client renders the theme's 404 template.
        if (! $request->header('X-Inertia')) {
            $httpResponse->setStatusCode($context['status']);
        }

        return $httpResponse;
    }

    /**
     * Unlock a password-protected post for this session.
     */
    public function unlock(Request $request, Post $post)
    {
        $data = $request->validate(['password' => 'required|string']);

        $ok = $post->password && (hash_equals((string) $post->password, $data['password']) || (str_starts_with((string) $post->password, '$2y$') && Hash::check($data['password'], $post->password)));

        if (! $ok) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put('post_password_'.$post->id, true);

        return redirect($post->permalink);
    }
}
