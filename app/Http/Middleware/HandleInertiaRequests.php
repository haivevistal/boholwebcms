<?php

namespace App\Http\Middleware;

use App\Cms\Admin\AdminMenu;
use App\Cms\Extensions\PluginManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'admin';

    public function rootView(Request $request): string
    {
        return $this->isAdmin($request) || $request->routeIs('login', 'register', 'password.*', 'install.*') ? 'admin' : 'front';
    }

    public function version(Request $request): ?string
    {
        // Theme / plugin asset changes also invalidate the client.
        return md5((string) parent::version($request).'|'.get_option('active_theme').'|'.json_encode(get_option('active_plugins')));
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $shared = [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatarUrl(64),
                    'can' => [
                        'admin' => current_user_can('read'),
                        'edit_posts' => current_user_can('edit_posts'),
                        'manage_options' => current_user_can('manage_options'),
                        'edit_theme_options' => current_user_can('edit_theme_options'),
                    ],
                ] : null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ],
            'site' => fn () => [
                'name' => cms_installed() ? get_option('blogname', config('app.name')) : config('app.name'),
                'url' => url('/'),
            ],
            'cms' => [
                'name' => config('cms.name'),
                'version' => cms_version(),
            ],
        ];

        if ($this->isAdmin($request) && $user && cms_installed()) {
            $shared['adminMenu'] = fn () => app(AdminMenu::class)->toArray();
            $shared['adminNotices'] = fn () => $this->notices();
            $shared['adminBar'] = fn () => apply_filters('admin_bar_items', []);
        }

        return $shared;
    }

    protected function notices(): array
    {
        $notices = [];

        if (current_user_can('activate_plugins')) {
            foreach (app(PluginManager::class)->errors() as $key => $message) {
                $notices[] = ['type' => 'error', 'message' => "Plugin {$key}: {$message}", 'html' => false, 'dismiss' => url('/admin/plugins/dismiss-errors')];
            }
        }
        if (current_user_can('switch_themes') && ($err = get_option('theme_error'))) {
            $notices[] = ['type' => 'error', 'message' => "Theme error: {$err}", 'html' => false, 'dismiss' => url('/admin/themes/dismiss-error')];
        }
        if (current_user_can('manage_options') && get_option('discourage_search_engines')) {
            $notices[] = ['type' => 'warning', 'message' => 'Search engines are discouraged from indexing this site (Settings → Reading).', 'html' => false];
        }

        return array_values(apply_filters('admin_notices', $notices));
    }

    protected function isAdmin(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }
}
