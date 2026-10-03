<?php

namespace App\Providers;

use App\Cms\Admin\AdminMenu;
use App\Cms\Admin\DashboardWidgets;
use App\Cms\Admin\MetaBoxRegistry;
use App\Cms\Admin\SettingsRegistry;
use App\Cms\Admin\ToolsRegistry;
use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Cms\Core\CoreAdmin;
use App\Cms\Core\CoreContent;
use App\Cms\Extensions\FileEditor;
use App\Cms\Extensions\PluginManager;
use App\Cms\Extensions\ThemeManager;
use App\Cms\Extensions\ZipInstaller;
use App\Cms\Hooks\HookManager;
use App\Cms\Shortcodes\ShortcodeManager;
use App\Cms\Support\Assets;
use App\Cms\Support\Capabilities;
use App\Cms\Support\HtmlSanitizer;
use App\Cms\Support\Options;
use App\Cms\Support\Permalinks;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the CMS in the same order as WordPress:
 *
 *   core types & admin  →  active plugins  →  plugins_loaded
 *   →  theme functions.php  →  after_setup_theme  →  init  →  cms_loaded
 */
class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            HookManager::class, Options::class, Capabilities::class, Assets::class, Permalinks::class,
            AdminMenu::class, SettingsRegistry::class, MetaBoxRegistry::class, DashboardWidgets::class, ToolsRegistry::class,
            PostTypeRegistry::class, TaxonomyRegistry::class, ZipInstaller::class, FileEditor::class, HtmlSanitizer::class,
            \App\Cms\Support\ImageProcessor::class, \App\Cms\Frontend\ThemeData::class, \App\Cms\Frontend\RequestResolver::class,
        ] as $service) {
            $this->app->singleton($service);
        }

        $this->app->singleton(ShortcodeManager::class, fn () => new ShortcodeManager(
            fn (string $hook, mixed $value, mixed ...$args) => apply_filters($hook, $value, ...$args)
        ));

        $this->app->singleton(PluginManager::class, fn ($app) => new PluginManager($app['config']->get('cms.plugins_path')));
        $this->app->singleton(ThemeManager::class, fn ($app) => new ThemeManager($app['config']->get('cms.themes_path')));
    }

    public function boot(): void
    {
        if (! defined('CMS_LOADED')) {
            define('CMS_LOADED', true);
        }

        // Laravel Gate checks (@can, $this->authorize, can: middleware)
        // fall through to CMS capabilities.
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($user instanceof User && user_can($user, $ability, ...$arguments)) {
                return true;
            }

            return null;
        });

        if (! cms_installed()) {
            return;
        }

        app(CoreContent::class)->register();
        app(CoreAdmin::class)->register();

        do_action('muplugins_loaded');

        $plugins = app(PluginManager::class);

        // Plugin migrations are picked up by `php artisan migrate` while active.
        $this->loadMigrationsFrom($plugins->activeMigrationPaths());

        $plugins->loadActive();
        do_action('plugins_loaded');

        $themes = app(ThemeManager::class);
        add_action('enqueue_scripts', [app(\App\Cms\Frontend\ThemeData::class), 'enqueueThemeAssets'], 1);
        $themes->loadFunctions();
        do_action('after_setup_theme');

        do_action('init');
        do_action('cms_loaded');
    }
}
