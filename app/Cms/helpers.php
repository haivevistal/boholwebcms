<?php

/*
|--------------------------------------------------------------------------
| BoholwebCMS public API
|--------------------------------------------------------------------------
| WordPress-style global functions available to plugins, themes
| (functions.php) and the core. Everything here is a thin wrapper around a
| service in the container, so it can be swapped or decorated.
*/

use App\Cms\Admin\AdminMenu;
use App\Cms\Admin\DashboardWidgets;
use App\Cms\Admin\MetaBoxRegistry;
use App\Cms\Admin\SettingsRegistry;
use App\Cms\Admin\ToolsRegistry;
use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Cms\Extensions\PluginManager;
use App\Cms\Extensions\ThemeManager;
use App\Cms\Hooks\HookManager;
use App\Cms\Shortcodes\ShortcodeManager;
use App\Cms\Support\Assets;
use App\Cms\Support\Capabilities;
use App\Cms\Support\Options;
use App\Cms\Support\Permalinks;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;

/* -------------------------------------------------------------------------
 | Core
 * ---------------------------------------------------------------------- */

if (! function_exists('cms_installed')) {
    function cms_installed(): bool
    {
        static $installed = false;

        return $installed = $installed || is_file(config('cms.installed_marker'));
    }
}

if (! function_exists('cms_version')) {
    function cms_version(): string
    {
        return (string) config('cms.version');
    }
}

/* -------------------------------------------------------------------------
 | Actions & filters
 * ---------------------------------------------------------------------- */

if (! function_exists('add_action')) {
    function add_action(string $hook, callable|array|string $callback, int $priority = 10, ?int $acceptedArgs = null): bool
    {
        return app(HookManager::class)->addAction($hook, $callback, $priority, $acceptedArgs);
    }

    function do_action(string $hook, mixed ...$args): void
    {
        app(HookManager::class)->doAction($hook, ...$args);
    }

    function add_filter(string $hook, callable|array|string $callback, int $priority = 10, ?int $acceptedArgs = null): bool
    {
        return app(HookManager::class)->addFilter($hook, $callback, $priority, $acceptedArgs);
    }

    function apply_filters(string $hook, mixed $value = null, mixed ...$args): mixed
    {
        return app(HookManager::class)->applyFilters($hook, $value, ...$args);
    }

    function remove_action(string $hook, callable|array|string $callback, ?int $priority = null): bool
    {
        return app(HookManager::class)->removeAction($hook, $callback, $priority);
    }

    function remove_filter(string $hook, callable|array|string $callback, ?int $priority = null): bool
    {
        return app(HookManager::class)->removeFilter($hook, $callback, $priority);
    }

    function remove_all_actions(string $hook, ?int $priority = null): void
    {
        app(HookManager::class)->removeAll($hook, $priority);
    }

    function remove_all_filters(string $hook, ?int $priority = null): void
    {
        app(HookManager::class)->removeAll($hook, $priority);
    }

    function has_action(string $hook, callable|array|string|null $callback = null): bool|int
    {
        return app(HookManager::class)->hasAction($hook, $callback);
    }

    function has_filter(string $hook, callable|array|string|null $callback = null): bool|int
    {
        return app(HookManager::class)->hasFilter($hook, $callback);
    }

    function did_action(string $hook): int
    {
        return app(HookManager::class)->didAction($hook);
    }

    function current_filter(): ?string
    {
        return app(HookManager::class)->currentFilter();
    }

    function current_action(): ?string
    {
        return app(HookManager::class)->currentFilter();
    }

    function doing_filter(?string $hook = null): bool
    {
        return app(HookManager::class)->doingFilter($hook);
    }

    function doing_action(?string $hook = null): bool
    {
        return app(HookManager::class)->doingFilter($hook);
    }

    /** Capture the output of an action as a string (e.g. for theme regions). */
    function capture_action(string $hook, mixed ...$args): string
    {
        ob_start();
        do_action($hook, ...$args);

        return (string) ob_get_clean();
    }
}

/* -------------------------------------------------------------------------
 | Shortcodes
 * ---------------------------------------------------------------------- */

if (! function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        app(ShortcodeManager::class)->add($tag, $callback);
    }

    function remove_shortcode(string $tag): void
    {
        app(ShortcodeManager::class)->remove($tag);
    }

    function remove_all_shortcodes(): void
    {
        app(ShortcodeManager::class)->removeAll();
    }

    function shortcode_exists(string $tag): bool
    {
        return app(ShortcodeManager::class)->exists($tag);
    }

    function has_shortcode(string $content, string $tag): bool
    {
        return app(ShortcodeManager::class)->has($content, $tag);
    }

    function do_shortcode(?string $content, bool $ignoreHtml = false): string
    {
        return app(ShortcodeManager::class)->process((string) $content, $ignoreHtml);
    }

    function strip_shortcodes(?string $content): string
    {
        return app(ShortcodeManager::class)->strip((string) $content);
    }

    function shortcode_atts(array $defaults, array|string|null $atts, string $shortcode = ''): array
    {
        return app(ShortcodeManager::class)->atts($defaults, $atts, $shortcode);
    }

    function shortcode_parse_atts(string $text): array
    {
        return app(ShortcodeManager::class)->parseAtts($text);
    }
}

/* -------------------------------------------------------------------------
 | Options & theme mods
 * ---------------------------------------------------------------------- */

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = null): mixed
    {
        return app(Options::class)->get($name, $default);
    }

    function update_option(string $name, mixed $value, ?bool $autoload = null): bool
    {
        return app(Options::class)->update($name, $value, $autoload);
    }

    function add_option(string $name, mixed $value, bool $autoload = true): bool
    {
        return app(Options::class)->add($name, $value, $autoload);
    }

    function delete_option(string $name): bool
    {
        return app(Options::class)->delete($name);
    }

    function get_theme_mod(string $name, mixed $default = null): mixed
    {
        return app(ThemeManager::class)->getMod($name, $default);
    }

    function set_theme_mod(string $name, mixed $value): void
    {
        app(ThemeManager::class)->setMod($name, $value);
    }

    function remove_theme_mod(string $name): void
    {
        app(ThemeManager::class)->removeMod($name);
    }

    function get_theme_mods(): array
    {
        return app(ThemeManager::class)->mods();
    }

    function get_bloginfo(string $show = 'name'): string
    {
        return (string) match ($show) {
            'name' => get_option('blogname', config('app.name')),
            'description' => get_option('blogdescription', ''),
            'url', 'wpurl', 'siteurl' => home_url(),
            'admin_email' => get_option('admin_email', ''),
            'language' => str_replace('_', '-', (string) get_option('site_language', 'en')),
            'version' => cms_version(),
            'charset' => 'UTF-8',
            default => get_option($show, ''),
        };
    }
}

/* -------------------------------------------------------------------------
 | Theme support flags
 * ---------------------------------------------------------------------- */

if (! function_exists('add_theme_support')) {
    function add_theme_support(string $feature, mixed $args = true): void
    {
        $features = app()->bound('cms.theme_support') ? app('cms.theme_support') : [];
        $features[$feature] = $args;
        app()->instance('cms.theme_support', $features);
    }

    function current_theme_supports(string $feature): bool
    {
        return isset((app()->bound('cms.theme_support') ? app('cms.theme_support') : [])[$feature]);
    }

    function get_theme_support(string $feature): mixed
    {
        return (app()->bound('cms.theme_support') ? app('cms.theme_support') : [])[$feature] ?? null;
    }
}

/* -------------------------------------------------------------------------
 | Users & capabilities
 * ---------------------------------------------------------------------- */

if (! function_exists('current_user_can')) {
    function current_user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    function current_user_id(): int
    {
        return (int) (auth()->id() ?? 0);
    }

    function is_user_logged_in(): bool
    {
        return auth()->check();
    }

    function current_user_can(string $capability, mixed ...$args): bool
    {
        return app(Capabilities::class)->userCan(current_user(), $capability, ...$args);
    }

    function user_can(User|int|null $user, string $capability, mixed ...$args): bool
    {
        if (is_int($user)) {
            $user = User::find($user);
        }

        return app(Capabilities::class)->userCan($user, $capability, ...$args);
    }

    function get_user_meta(int $userId, string $key, mixed $default = null): mixed
    {
        return User::find($userId)?->getMeta($key, $default) ?? $default;
    }

    function update_user_meta(int $userId, string $key, mixed $value): void
    {
        User::find($userId)?->setMeta($key, $value);
    }

    function delete_user_meta(int $userId, string $key): void
    {
        User::find($userId)?->deleteMeta($key);
    }
}

/* -------------------------------------------------------------------------
 | Admin menu, admin pages, settings, tools, dashboard, meta boxes, notices
 * ---------------------------------------------------------------------- */

if (! function_exists('add_menu_page')) {
    function add_menu_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, ?string $icon = null, int|float|null $position = null): string
    {
        return app(AdminMenu::class)->addMenuPage($pageTitle, $menuTitle, $capability, $menuSlug, $callback, $icon, $position);
    }

    function add_submenu_page(string $parentSlug, string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, int|float|null $position = null): string
    {
        return app(AdminMenu::class)->addSubmenuPage($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
    }

    /** Settings submenu shortcut (like add_options_page). */
    function add_options_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, int|float|null $position = null): string
    {
        return add_submenu_page('settings', $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
    }

    /** Tools submenu shortcut (like add_management_page). */
    function add_management_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, int|float|null $position = null): string
    {
        return add_submenu_page('tools', $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
    }

    /** Appearance submenu shortcut (like add_theme_page). */
    function add_theme_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, int|float|null $position = null): string
    {
        return add_submenu_page('appearance', $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
    }

    /** Users submenu shortcut. */
    function add_users_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable|array|string|null $callback = null, int|float|null $position = null): string
    {
        return add_submenu_page('users', $pageTitle, $menuTitle, $capability, $menuSlug, $callback, $position);
    }

    /** An admin page without a menu entry: /admin/page/{slug}. */
    function add_admin_page(string $slug, string $title, string $capability, callable|array|string $callback): void
    {
        app(AdminMenu::class)->addPage($slug, $title, $capability, $callback);
    }

    function remove_menu_page(string $menuSlug): void
    {
        app(AdminMenu::class)->removeMenuPage($menuSlug);
    }

    function remove_submenu_page(string $parentSlug, string $menuSlug): void
    {
        app(AdminMenu::class)->removeSubmenuPage($parentSlug, $menuSlug);
    }

    function admin_page_url(string $slug, array $query = []): string
    {
        return url('/admin/page/'.$slug).($query ? '?'.http_build_query($query) : '');
    }

    function admin_url(string $path = ''): string
    {
        return url('/admin/'.ltrim($path, '/'));
    }

    function register_settings_page(string $slug, array $args): void
    {
        app(SettingsRegistry::class)->register($slug, $args);
    }

    function add_settings_section(string $page, string $id, ?string $title = null, ?string $description = null): void
    {
        app(SettingsRegistry::class)->addSection($page, ['id' => $id, 'title' => $title, 'description' => $description]);
    }

    function add_settings_field(string $page, string $section, array $field): void
    {
        app(SettingsRegistry::class)->addField($page, $section, $field);
    }

    function remove_settings_field(string $page, string $name): void
    {
        app(SettingsRegistry::class)->removeField($page, $name);
    }

    function register_tool(string $slug, array $args): void
    {
        app(ToolsRegistry::class)->add($slug, $args);
    }

    function add_dashboard_widget(string $id, string $title, ?callable $callback = null, array $args = []): void
    {
        app(DashboardWidgets::class)->add($id, $title, $callback, $args);
    }

    function remove_dashboard_widget(string $id): void
    {
        app(DashboardWidgets::class)->remove($id);
    }

    function add_meta_box(string $id, string $title, string|array $screens, array $args = []): void
    {
        app(MetaBoxRegistry::class)->add($id, $title, $screens, $args);
    }

    function remove_meta_box(string $id): void
    {
        app(MetaBoxRegistry::class)->remove($id);
    }

    /**
     * Show a notice at the top of admin screens for this request.
     * $type: info | success | warning | error
     */
    function add_admin_notice(string $message, string $type = 'info', bool $html = false): void
    {
        add_filter('admin_notices', function (array $notices) use ($message, $type, $html) {
            $notices[] = compact('message', 'type', 'html');

            return $notices;
        });
    }
}

/* -------------------------------------------------------------------------
 | Content: post types, taxonomies, posts, meta, terms, permalinks
 * ---------------------------------------------------------------------- */

if (! function_exists('register_post_type')) {
    function register_post_type(string $name, array $args = []): array
    {
        $type = app(PostTypeRegistry::class)->register($name, $args);
        foreach ($type['taxonomies'] as $tax) {
            app(TaxonomyRegistry::class)->attach($tax, $name);
        }

        return $type;
    }

    function register_taxonomy(string $name, string|array $objectTypes, array $args = []): array
    {
        return app(TaxonomyRegistry::class)->register($name, $objectTypes, $args);
    }

    function register_taxonomy_for_object_type(string $taxonomy, string $postType): void
    {
        app(TaxonomyRegistry::class)->attach($taxonomy, $postType);
    }

    function post_type_exists(string $name): bool
    {
        return app(PostTypeRegistry::class)->exists($name);
    }

    function taxonomy_exists(string $name): bool
    {
        return app(TaxonomyRegistry::class)->exists($name);
    }

    function get_post_type_object(string $name): ?array
    {
        return app(PostTypeRegistry::class)->get($name);
    }

    function get_post_types(bool $publicOnly = false): array
    {
        $registry = app(PostTypeRegistry::class);

        return array_keys($publicOnly ? $registry->public() : $registry->all());
    }

    function get_object_taxonomies(string $postType): array
    {
        return array_keys(app(TaxonomyRegistry::class)->forType($postType));
    }

    function post_type_supports(string $postType, string $feature): bool
    {
        return app(PostTypeRegistry::class)->supports($postType, $feature);
    }

    function get_post(int|Post|null $post): ?Post
    {
        return $post instanceof Post ? $post : ($post ? Post::find($post) : null);
    }

    /**
     * Query posts. Args: type, status, limit, offset, orderby, order, include, exclude,
     * author, parent, search, meta_key, meta_value, term (id|slug), taxonomy.
     */
    function get_posts(array $args = []): \Illuminate\Support\Collection
    {
        return Post::queryFromArgs($args)->get();
    }

    /**
     * Create or update a post (like wp_insert_post). Pass 'id' to update.
     */
    function cms_insert_post(array $data): Post
    {
        return Post::insertFromArray($data);
    }

    function get_post_meta(int $postId, string $key, mixed $default = null): mixed
    {
        return Post::find($postId)?->getMeta($key, $default) ?? $default;
    }

    function update_post_meta(int $postId, string $key, mixed $value): void
    {
        Post::find($postId)?->setMeta($key, $value);
    }

    function delete_post_meta(int $postId, string $key): void
    {
        Post::find($postId)?->deleteMeta($key);
    }

    function get_permalink(int|Post $post): ?string
    {
        $post = get_post($post);

        return $post ? $post->permalink : null;
    }

    function get_term_link(int|Term $term): ?string
    {
        $term = $term instanceof Term ? $term : Term::find($term);

        return $term ? app(Permalinks::class)->term($term) : null;
    }

    function get_post_type_archive_link(string $postType): ?string
    {
        return app(Permalinks::class)->archive($postType);
    }

    function get_the_terms(int|Post $post, string $taxonomy): \Illuminate\Support\Collection
    {
        $post = get_post($post);

        return $post ? $post->terms->where('taxonomy', $taxonomy)->values() : collect();
    }

    function get_terms(string $taxonomy, array $args = []): \Illuminate\Support\Collection
    {
        $q = Term::where('taxonomy', $taxonomy);
        if (isset($args['parent'])) {
            $q->where('parent_id', $args['parent'] ?: null);
        }
        if (! empty($args['hide_empty'])) {
            $q->where('count', '>', 0);
        }

        return $q->orderBy($args['orderby'] ?? 'name', $args['order'] ?? 'asc')->get();
    }

    function add_image_size(string $name, int $width, int $height, bool $crop = false): void
    {
        app(\App\Cms\Support\ImageProcessor::class)->addSize($name, $width, $height, $crop);
    }

    function home_url(string $path = ''): string
    {
        return app(Permalinks::class)->home($path);
    }

    function site_url(string $path = ''): string
    {
        return home_url($path);
    }
}

/* -------------------------------------------------------------------------
 | Plugins & themes
 * ---------------------------------------------------------------------- */

if (! function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void
    {
        if ($key = app(PluginManager::class)->keyForFile($file)) {
            add_action("activate_{$key}", $callback);
        }
    }

    function register_deactivation_hook(string $file, callable $callback): void
    {
        if ($key = app(PluginManager::class)->keyForFile($file)) {
            add_action("deactivate_{$key}", $callback);
        }
    }

    function register_uninstall_hook(string $file, callable $callback): void
    {
        if ($key = app(PluginManager::class)->keyForFile($file)) {
            add_action("uninstall_{$key}", $callback);
        }
    }

    function is_plugin_active(string $key): bool
    {
        $manager = app(PluginManager::class);
        if (! str_contains($key, '/') && ! str_ends_with($key, '.php')) {
            // Accept a bare folder slug.
            foreach ($manager->activeKeys() as $active) {
                if (str_starts_with($active, $key.'/')) {
                    return true;
                }
            }

            return false;
        }

        return $manager->isActive($key);
    }

    function plugin_basename(string $file): string
    {
        return app(PluginManager::class)->keyForFile($file) ?? basename($file);
    }

    function plugin_dir_path(string $file): string
    {
        return rtrim(dirname($file), '/').'/';
    }

    /**
     * Public URL of a file inside a plugin: plugin_url(__FILE__, 'assets/app.js')
     */
    function plugin_url(string $file, string $path = ''): string
    {
        $root = realpath(config('cms.plugins_path')) ?: config('cms.plugins_path');
        $dir = realpath(dirname($file)) ?: dirname($file);
        $relative = trim(str_replace('\\', '/', substr($dir, strlen($root))), '/');

        return content_url('plugins/'.$relative.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }

    function content_url(string $path = ''): string
    {
        return url('/'.trim(config('cms.content_url_prefix', 'content'), '/').'/'.ltrim($path, '/'));
    }

    function get_stylesheet(): string
    {
        return app(ThemeManager::class)->activeSlug();
    }

    function get_template_directory(): string
    {
        $stack = app(ThemeManager::class)->stack();

        return $stack ? $stack[0]['dir'] : '';
    }

    function get_stylesheet_directory(): string
    {
        return app(ThemeManager::class)->active()['dir'] ?? '';
    }

    function theme_url(string $path = ''): string
    {
        return content_url('themes/'.get_stylesheet().($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

/* -------------------------------------------------------------------------
 | Scripts & styles
 * ---------------------------------------------------------------------- */

if (! function_exists('enqueue_script')) {
    function enqueue_script(string $handle, string $src, array $deps = [], ?string $version = null, bool $inFooter = true, array $attributes = []): void
    {
        app(Assets::class)->enqueueScript($handle, $src, $deps, $version, $inFooter, $attributes);
    }

    function enqueue_style(string $handle, string $src, array $deps = [], ?string $version = null, string $media = 'all'): void
    {
        app(Assets::class)->enqueueStyle($handle, $src, $deps, $version, $media);
    }

    function dequeue_script(string $handle): void
    {
        app(Assets::class)->dequeueScript($handle);
    }

    function dequeue_style(string $handle): void
    {
        app(Assets::class)->dequeueStyle($handle);
    }

    function localize_script(string $handle, string $objectName, array $data): void
    {
        app(Assets::class)->localizeScript($handle, $objectName, $data);
    }

    function add_inline_script(string $code, string $position = 'footer'): void
    {
        app(Assets::class)->addInline($position, $code, 'script');
    }

    function add_inline_style(string $css): void
    {
        app(Assets::class)->addInline('head', $css, 'style');
    }

    /** Print everything hooked to the document <head> (front end or admin). */
    function cms_head(string $context = 'front'): string
    {
        $assets = app(Assets::class);
        $assets->setContext($context);

        ob_start();
        if ($context === 'admin') {
            do_action('admin_enqueue_scripts');
            do_action('admin_head');
        } else {
            do_action('enqueue_scripts');
            do_action('cms_head');
        }
        $actions = (string) ob_get_clean();

        return $assets->renderHead().$actions;
    }

    /** Print everything hooked before </body>. */
    function cms_footer(string $context = 'front'): string
    {
        $assets = app(Assets::class);
        $assets->setContext($context);

        ob_start();
        do_action($context === 'admin' ? 'admin_footer' : 'cms_footer');
        $actions = (string) ob_get_clean();

        return $assets->renderFooter().$actions;
    }
}

/* -------------------------------------------------------------------------
 | Responses from inside hooks (like wp_send_json / wp_redirect + exit)
 * ---------------------------------------------------------------------- */

if (! function_exists('cms_send_response')) {
    /**
     * Stop the request and send this response — usable from any action.
     */
    function cms_send_response(\Symfony\Component\HttpFoundation\Response $response): never
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }

    function cms_send_json(mixed $data = null, int $status = 200): never
    {
        cms_send_response(response()->json(['success' => $status < 400, 'data' => $data], $status));
    }

    function cms_send_json_success(mixed $data = null): never
    {
        cms_send_json($data, 200);
    }

    function cms_send_json_error(mixed $data = null, int $status = 400): never
    {
        cms_send_response(response()->json(['success' => false, 'data' => $data], $status));
    }

    function cms_redirect(string $url, int $status = 302): never
    {
        cms_send_response(redirect()->to($url, $status));
    }
}

/* -------------------------------------------------------------------------
 | Formatting & escaping helpers
 * ---------------------------------------------------------------------- */

if (! function_exists('esc_html')) {
    function esc_html(mixed $text): string
    {
        return e((string) $text);
    }

    function esc_attr(mixed $text): string
    {
        return e((string) $text);
    }

    function esc_url(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $url)) {
            return '';
        }

        return e($url);
    }

    function esc_js(mixed $text): string
    {
        return trim(json_encode((string) $text, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), '"');
    }

    function sanitize_text_field(mixed $text): string
    {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $text)));
    }

    function sanitize_textarea_field(mixed $text): string
    {
        return trim(strip_tags((string) $text));
    }

    function sanitize_title(mixed $title): string
    {
        return Str::slug((string) $title);
    }

    function sanitize_key(mixed $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }

    function selected(mixed $a, mixed $b = true): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }

    function checked(mixed $a, mixed $b = true): string
    {
        return (string) $a === (string) $b ? ' checked' : '';
    }

    /**
     * Allow-list based HTML sanitizer used for users without unfiltered_html.
     */
    function kses_post(?string $html): string
    {
        return app(\App\Cms\Support\HtmlSanitizer::class)->clean((string) $html);
    }

    function cms_trim_words(string $text, int $words = 55, string $more = '…'): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(strip_shortcodes($text))));

        return Str::words($text, $words, $more);
    }

    function format_cms_date(mixed $date, ?string $format = null): string
    {
        if (! $date) {
            return '';
        }
        $carbon = $date instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($date) : \Illuminate\Support\Carbon::parse($date);

        return $carbon->timezone((string) get_option('timezone_string', config('app.timezone')) ?: 'UTC')
            ->format($format ?? (string) get_option('date_format', 'F j, Y'));
    }
}
