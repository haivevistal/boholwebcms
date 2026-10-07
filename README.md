# BoholwebCMS

A WordPress-style CMS built on **Laravel 12 or 13 + Inertia.js + React**. Install plugins and themes from `.zip` files, extend everything with **actions, filters and shortcodes**, and add your own admin menus and pages — the way WordPress developers already know.

- **Plugins** live in `content/plugins/{slug}`. They have a WordPress-style header, activation/deactivation/uninstall hooks, their own migrations, and they load on every request while active.
- **Themes** live in `content/themes/{slug}`. They're React bundles described by `theme.json`, with a `functions.php`, a Customizer, page templates, regions (widget areas), menus and child-theme support.
- **Every feature is hookable**: content (`the_content`, `the_title`, …), queries, permalinks, admin menus, list tables, editors, settings, dashboard, auth, comments, users, media, and the front end (PHP *and* JS hooks).

---

## Requirements

PHP 8.2+ (ext: `pdo_sqlite` or `pdo_mysql`, `zip`, `gd`, `mbstring`), Composer 2, Node 20+.

## Installation

### Web installer (like WordPress)

1. Upload the files to your server and point the web root (document root) at the **`public/`** folder.
2. Run `composer install --no-dev --optimize-autoloader` in the site folder (the page tells you if you forgot).
3. Open your site in the browser — you are sent to **`/install`**, a 4-step wizard:
   - **Server check** — PHP version, extensions, database drivers and writable folders.
   - **Database** — choose **MySQL, MariaDB, PostgreSQL, SQL Server or SQLite**, enter host, port, database name, username, password and a **table prefix** (e.g. `bw_`, so several sites can share one database). *Test connection* checks the credentials, shows the server version, warns if the prefix is already in use, and offers to **create the database** if it doesn’t exist.
   - **Site information** — site title, tagline, site URL, timezone, administrator username / email / password (with generator and strength meter), starting theme, sample content, the Simple Shop demo, search-engine visibility and production mode.
   - **Install** — saves everything to `.env` (the `wp-config.php` equivalent), creates the tables, roles, admin account, settings and sample content, links `public/storage`, and shows a step-by-step log, then a **Log In** button.

On a fresh server the installer creates `.env` from `.env.example` and generates the `APP_KEY` automatically (`bootstrap/preinstall.php`). The site folder (or at least `.env`), `storage/`, `bootstrap/cache/` and `content/` must be writable by the web server. Once installed, `/install` is locked; to reinstall, delete `storage/app/.cms-installed` and the database tables.

### Command line

```bash
composer install
cp .env.example .env               # set DB_CONNECTION / DB_* (SQLite works out of the box)
php artisan key:generate
php artisan cms:install            # --title= --username= --email= --password= --theme= --demo --no-sample --fresh --force
php artisan serve                  # http://localhost:8000  —  admin at /admin
```

Front-end assets are prebuilt; run `npm install && npm run build:all` only after changing the React sources or themes.

Production: run `php artisan storage:link` if the installer couldn’t, add the scheduler (`* * * * * php artisan schedule:run`) so `cms_cron` fires (scheduled posts), and set `CMS_DISALLOW_FILE_EDIT=true`.

Useful commands:

```bash
php artisan cms:plugin list | activate simple-shop | deactivate simple-shop | delete simple-shop | make "My Plugin"
php artisan cms:theme list | activate minimal
npm run build:themes -- aurora           # rebuild one theme   (node theme-kit/build-all.mjs aurora --watch)
php artisan test                         # PHPUnit suite
php tests/standalone/run.php             # framework-free core smoke tests
php tests/standalone/install.php         # installer pieces (.env writer, DB probe, requirements)
```

---

## Admin

| Menu | Screens |
|---|---|
| Dashboard | Welcome, At a Glance, Activity, Quick Draft + plugin widgets |
| Posts | All Posts, Add Post, Categories, Tags |
| Comments | Moderation queue, approve/spam/trash, reply, edit, bulk actions |
| Media | Library grid, drag & drop upload, auto image sizes, details |
| Pages | All Pages, Add Page (hierarchy, templates, order) |
| Appearance | Themes (upload/activate/delete), Customize (live preview), Theme File Editor |
| Plugins | Installed Plugins, Add Plugin (zip upload or scaffold), Plugin File Editor |
| Users | All Users, Add User, Roles (capability editor), Profile |
| Tools | Available Tools (Export, Import, Site Health, Hooks Inspector, Clear Cache + plugin tools) |
| Settings | General, Writing, Reading, Discussion, Media, Permalinks, Privacy + plugin pages |

Custom post types registered by plugins get their own menu, list table and editor automatically.

---

## Writing a plugin

```php
<?php
/**
 * Plugin Name: Hello Banner
 * Description: Adds a banner, a shortcode, an admin page and a setting.
 * Version: 1.0.0
 * Author: You
 */

defined('CMS_LOADED') || exit;

register_activation_hook(__FILE__, fn () => add_option('hello_text', 'Hello world!'));

// Shortcode: [hello name="Ann"]
add_shortcode('hello', function (array $atts, ?string $content, string $tag) {
    $atts = shortcode_atts(['name' => 'friend'], $atts, $tag);
    return '<strong>Hello '.e($atts['name']).'</strong>';
});

// Filter content site-wide
add_filter('the_content', fn ($html) => '<div class="banner">'.e(get_option('hello_text')).'</div>'.$html);

// Admin: a top-level menu, plus pages under core menus
add_action('admin_menu', function () {
    add_menu_page('Hello', 'Hello', 'manage_options', 'hello', fn () => '<p>Plain HTML page</p>', 'sparkles', 30);
    add_submenu_page('tools', 'Hello Tool', 'Hello Tool', 'manage_options', 'hello-tool', fn ($request) => '<p>Under Tools</p>');
    add_submenu_page('hello', 'React page', 'React page', 'manage_options', 'hello-react', fn () => ['component' => 'hello/Page', 'props' => ['x' => 1]]);
    remove_submenu_page('tools', 'available-tools'); // core items can be removed too
});

// A settings page that appears under Settings → Hello
register_settings_page('hello', [
    'title' => 'Hello Settings',
    'sections' => [['id' => 'main', 'fields' => [
        ['name' => 'hello_text', 'label' => 'Banner text', 'type' => 'text'],
    ]]],
]);

// Front-end routes, AJAX and assets
add_action('cms_routes', fn ($router) => $router->get('/hello', fn () => 'Hi!'));
add_action('ajax_nopriv_hello_ping', fn ($request) => cms_send_json(['pong' => true]));
add_action('enqueue_scripts', fn () => enqueue_script('hello', plugin_url(__FILE__, 'assets/hello.js')));
```

Zip the folder and upload it in **Plugins → Add Plugin**, or create it from there with **Create a New Plugin**.

**Conventions:** `migrations/` (run on activation and by `php artisan migrate` while active) · `uninstall.php` (runs on delete) · `assets/` (served at `/content/plugins/{slug}/…`) · `Requires Plugins: other-slug` header for dependencies. A plugin that throws while loading is deactivated automatically and the error is shown in the admin.

See **`content/plugins/simple-shop`** for a complete example that uses every extension point (custom post type + taxonomy, meta boxes, cart/checkout shortcodes, routes, AJAX, orders table, Shop menu with badge, Tools page, Settings page, dashboard widget, list-table columns, a React admin screen, a custom role and capability, uninstall cleanup).

### Admin page callbacks may return

- an **HTML string** — rendered inside the admin layout (WordPress-compatible CSS classes like `button button-primary`, `widefat`, `form-table`, `notice notice-success` are styled); forms and links inside are routed through Inertia automatically,
- `['component' => 'vendor/Name', 'props' => [...]]` — a React component registered from a plugin script with `CMS.registerAdminComponent('vendor/Name', Component)`,
- any **Response** (redirect, download, `Inertia::render('plugin:vendor/Page')` …).

Form handlers: `POST /admin/admin-post` with `action=xyz` fires `admin_post_xyz`; `POST /admin/ajax` fires `admin_ajax_xyz`. Use `cms_send_response()`, `cms_send_json()` or `cms_redirect()` to reply from inside a hook.

---

## Writing a theme

```
content/themes/my-theme/
├── theme.json        name, page_templates, regions, menus, customizer controls, parent
├── functions.php     optional — hooks & shortcodes (runs while active)
├── screenshot.png
├── src/index.jsx     export default { Layout, templates: { index, single, page, archive, search, 404 } }
└── dist/theme.js     built with `npm run build:themes -- my-theme`
```

The server resolves every URL (permalinks → query → **template hierarchy**) and the theme renders the first template it implements from `view.templates`, e.g. `["single-product", "single", "singular", "index"]`. Content arrives with shortcodes already rendered. Themes import helpers from `@boholwebcms/theme` (`Content`, `Menu`, `Region`, `Slot`, `Comments`, `Pagination`, `SearchForm`, `useThemeMod`, …) and `@inertiajs/react`. Full reference: **`theme-kit/README.md`**.

Included themes: **Aurora** (default; hero, accent colour, grid/list, sidebar, Full Width & Landing templates), **Minimal Journal** (typographic, dark mode), **Aurora Child** (child-theme example).

---

## API reference (PHP)

**Hooks** `add_action`, `do_action`, `add_filter`, `apply_filters`, `remove_action/filter`, `remove_all_actions/filters`, `has_action/filter`, `did_action`, `current_filter`, `doing_filter`, `capture_action`
**Shortcodes** `add_shortcode`, `remove_shortcode`, `shortcode_exists`, `has_shortcode`, `do_shortcode`, `strip_shortcodes`, `shortcode_atts`
**Options** `get_option`, `update_option`, `add_option`, `delete_option`, `get_theme_mod`, `set_theme_mod`, `get_theme_mods`, `get_bloginfo`
**Content** `register_post_type`, `register_taxonomy`, `get_posts`, `get_post`, `cms_insert_post`, `get/update/delete_post_meta`, `get_permalink`, `get_term_link`, `get_the_terms`, `get_terms`, `add_image_size`, `home_url`
**Users** `current_user`, `current_user_can`, `user_can`, `is_user_logged_in`, `get/update_user_meta`
**Admin** `add_menu_page`, `add_submenu_page`, `add_options_page`, `add_management_page`, `add_theme_page`, `add_users_page`, `add_admin_page`, `remove_menu_page`, `remove_submenu_page`, `register_settings_page`, `add_settings_section`, `add_settings_field`, `register_tool`, `add_dashboard_widget`, `add_meta_box`, `add_admin_notice`
**Plugins/themes** `register_activation_hook`, `register_deactivation_hook`, `register_uninstall_hook`, `is_plugin_active`, `plugin_url`, `plugin_dir_path`, `theme_url`, `add_theme_support`
**Assets** `enqueue_script`, `enqueue_style`, `localize_script`, `add_inline_script`, `add_inline_style`
**Responses** `cms_send_json`, `cms_send_json_error`, `cms_send_response`, `cms_redirect`
**Escaping** `esc_html`, `esc_attr`, `esc_url`, `sanitize_text_field`, `sanitize_title`, `kses_post`

### Common hooks

| Area | Actions | Filters |
|---|---|---|
| Boot | `muplugins_loaded`, `plugins_loaded`, `after_setup_theme`, `init`, `cms_loaded`, `cms_routes`, `cms_cron(_hourly/_daily)` | |
| Content | `save_post`, `save_post_{type}`, `transition_post_status`, `before_delete_post`, `deleted_post`, `trashed_post` | `the_content`, `the_title`, `get_the_excerpt`, `cms_insert_post_data`, `cms_post_data`, `post_public_meta`, `posts_query`, `pre_get_posts` |
| Front end | `template_redirect`, `enqueue_scripts`, `cms_head`, `cms_footer`, `cms_region_{name}` | `cms_resolve_request`, `template_hierarchy`, `theme_props`, `cms_nav_menu`, `document_title`, `document_meta`, `post_link`, `page_link`, `term_link` |
| Admin | `admin_init`, `admin_menu`, `admin_enqueue_scripts`, `admin_head`, `admin_footer`, `load-{slug}`, `admin_post_{action}`, `admin_ajax_{action}` | `admin_menu_items`, `admin_notices`, `manage_{type}_posts_columns`, `manage_{type}_posts_custom_column`, `post_row_actions`, `bulk_actions-{type}`, `settings_page_{slug}`, `dashboard_widgets`, `available_tools`, `customize_register`, `meta_boxes` |
| Plugins/themes | `activate_{key}`, `deactivate_{key}`, `activated_plugin`, `deleted_plugin`, `switch_theme`, `after_switch_theme` | `plugin_action_links_{key}` |
| Users & auth | `login`, `logout`, `user_register`, `profile_update`, `set_user_role`, `delete_user` | `authenticate`, `login_redirect`, `user_has_cap`, `map_meta_cap`, `cms_capabilities`, `user_profile_fields` |
| Comments | `comment_post`, `transition_comment_status`, `edit_comment` | `preprocess_comment`, `pre_comment_approved`, `comments_open`, `comment_text` |
| Options | `update_option`, `updated_option`, `update_option_{name}` | `pre_option_{name}`, `option_{name}`, `pre_update_option_{name}`, `sanitize_option_{name}` |

### JavaScript (`window.CMS`)

`CMS.hooks.addFilter/applyFilters/addAction/doAction` · `CMS.registerTheme` · `CMS.registerTemplate` (plugin front-end templates) · `CMS.registerAdminComponent` · `CMS.registerAdminPage` · `CMS.registerField` (custom field types) · `CMS.ajax(action, data)` · `CMS.adminAjax(action, data)` · `CMS.React`, `CMS.Inertia`, `CMS.components` (admin UI kit / theme helpers).
JS hooks fired by the core: `admin.init`, `front.init`, `theme.props`, `theme.templates`, `theme.rendered`, `theme.region.{name}`, `theme.slot.{name}`, `admin.editor.sidebar`, `admin.editor.main`, `content.rendered`. Plugin scripts can bind to server HTML with `document.addEventListener('cms:content', e => …)`.

---

## Architecture

```
app/Cms/Hooks/HookManager.php          actions & filters
app/Cms/Shortcodes/ShortcodeManager.php WordPress-compatible shortcode parser
app/Cms/Admin/*                        AdminMenu, SettingsRegistry, MetaBoxRegistry, DashboardWidgets, ToolsRegistry
app/Cms/Content/*                      PostTypeRegistry, TaxonomyRegistry
app/Cms/Extensions/*                   PluginManager, ThemeManager, ZipInstaller, FileEditor
app/Cms/Frontend/*                     RequestResolver (permalinks + template hierarchy), ThemeData (theme props)
app/Cms/Support/*                      Options, Capabilities, Assets, Permalinks, ImageProcessor, HtmlSanitizer
app/Cms/Core/*                         default content types, shortcodes, admin menus, settings, tools, widgets
app/Cms/helpers.php                    the public PHP API
resources/js/admin/*                   React admin (Inertia pages, layout, editor, media picker, field renderer)
resources/js/front/*                   theme runtime + @boholwebcms/theme helpers
resources/js/shared/*                  window.CMS runtime, JS hooks, registries, HtmlContent
theme-kit/                             theme bundler (React/Inertia are shared globals, not bundled)
```

Boot order (per request, `CmsServiceProvider`): core content & admin → active plugins → `plugins_loaded` → theme `functions.php` → `after_setup_theme` → `init` → `cms_loaded` → routes (`cms_routes`, then the front-end catch-all).

**Security notes:** content from users without `unfiltered_html` is sanitized; SVG uploads need `unfiltered_upload`; PHP files are syntax-checked before the file editors save them (and backed up to `storage/app/file-editor-backups`); zip uploads are checked for path traversal; only allow-listed asset types are served from `content/`. Plugins run with full PHP privileges — install only plugins you trust, exactly as with WordPress.


<!-- Security scan triggered at 2026-10-07 11:29:15 -->