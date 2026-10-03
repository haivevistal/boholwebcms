<?php

namespace App\Cms\Install;

use App\Cms\Extensions\PluginManager;
use App\Cms\Support\Capabilities;
use App\Cms\Support\Options;
use App\Models\Comment;
use App\Models\Option;
use App\Models\Post;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Installs BoholwebCMS. Used by the web installer (/install) and by
 * `php artisan cms:install` so both produce exactly the same result.
 */
class Installer
{
    public const DEMO_PLUGIN = 'simple-shop/simple-shop.php';

    /** @var array<int, array{step:string, ok:bool, detail:?string}> */
    protected array $log = [];

    /** @var callable|null */
    protected $listener = null;

    public function onStep(callable $listener): static
    {
        $this->listener = $listener;

        return $this;
    }

    public function log(): array
    {
        return $this->log;
    }

    /* ---------------------------------------------------------------------
     | Database & environment (web installer)
     * ------------------------------------------------------------------- */

    /**
     * Normalise the database form input.
     */
    public static function normalizeDatabase(array $input): array
    {
        $driver = $input['driver'] ?? 'mysql';
        $db = [
            'driver' => $driver,
            'host' => trim((string) ($input['host'] ?? '')) ?: '127.0.0.1',
            'port' => (string) ((int) ($input['port'] ?? 0) ?: (DatabaseProbe::DRIVERS[$driver]['port'] ?? '')),
            'database' => trim((string) ($input['database'] ?? '')),
            'username' => (string) ($input['username'] ?? ''),
            'password' => (string) ($input['password'] ?? ''),
            'prefix' => trim((string) ($input['prefix'] ?? '')),
        ];

        if ($driver === 'sqlite') {
            $path = $db['database'] ?: 'database/database.sqlite';
            $db['database'] = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) ? $path : base_path($path);
            $db['host'] = $db['port'] = $db['username'] = $db['password'] = '';
        }

        return $db;
    }

    /**
     * Point Laravel's connection at the submitted credentials for this request.
     */
    public function useDatabase(array $db): void
    {
        $driver = $db['driver'];
        $config = array_merge((array) config("database.connections.{$driver}"), array_filter([
            'host' => $db['host'] ?: null,
            'port' => $db['port'] ?: null,
            'database' => $db['database'],
            'username' => $driver === 'sqlite' ? null : $db['username'],
            'password' => $driver === 'sqlite' ? null : $db['password'],
        ], fn ($v) => $v !== null));
        $config['prefix'] = $db['prefix'];
        $config['url'] = null;

        config(["database.connections.{$driver}" => $config, 'database.default' => $driver]);
        DB::purge($driver);
        DB::setDefaultConnection($driver);
    }

    /**
     * Save database + site settings to .env (the wp-config.php equivalent).
     */
    public function writeEnvironment(array $db, array $site): void
    {
        $values = [
            'APP_URL' => rtrim($site['url'], '/'),
            'APP_ENV' => ! empty($site['production']) ? 'production' : 'local',
            'APP_DEBUG' => empty($site['production']),
            'DB_CONNECTION' => $db['driver'],
            'DB_HOST' => $db['driver'] === 'sqlite' ? null : $db['host'],
            'DB_PORT' => $db['driver'] === 'sqlite' ? null : $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['driver'] === 'sqlite' ? null : $db['username'],
            'DB_PASSWORD' => $db['driver'] === 'sqlite' ? null : $db['password'],
            'DB_PREFIX' => $db['prefix'],
        ];

        $this->step('Saving configuration to .env', function () use ($values) {
            (new EnvEditor(base_path('.env')))->set($values);
        });
    }

    public function prepareDatabase(array $db, bool $create): void
    {
        if ($db['driver'] === 'sqlite') {
            $this->step('Preparing SQLite database file', function () use ($db) {
                File::ensureDirectoryExists(dirname($db['database']));
                if (! is_file($db['database']) && ! touch($db['database'])) {
                    throw new RuntimeException('Could not create '.$db['database']);
                }
            });

            return;
        }

        if ($create) {
            $this->step("Creating database “{$db['database']}”", fn () => app(DatabaseProbe::class)->createDatabase($db));
        }
    }

    /* ---------------------------------------------------------------------
     | Installation (web + CLI)
     * ------------------------------------------------------------------- */

    /**
     * @param  array{title:string, description?:string, url?:string, admin_username:string, admin_email:string, admin_password:string,
     *               timezone?:string, theme?:string, discourage_search_engines?:bool, sample_content?:bool, demo?:bool, fresh?:bool}  $site
     */
    public function install(array $site): User
    {
        $this->step('Checking the database connection', function () {
            DB::connection()->getPdo();
        });

        $this->step('Creating database tables', function () use ($site) {
            Artisan::call(! empty($site['fresh']) ? 'migrate:fresh' : 'migrate', ['--force' => true]);
        });

        // Anything read before the tables existed must be forgotten.
        app(Options::class)->flush();
        app(Capabilities::class)->flush();

        $this->step('Creating user roles', function () {
            foreach (Capabilities::DEFAULT_ROLES as $slug => $role) {
                Role::updateOrCreate(['slug' => $slug], ['name' => $role['name'], 'capabilities' => $role['capabilities']]);
            }
        });

        $admin = null;
        $this->step('Creating the administrator account', function () use (&$admin, $site) {
            $existing = User::where('email', $site['admin_email'])->where('username', '!=', $site['admin_username'])->exists();
            if ($existing) {
                throw new RuntimeException('Another user already uses '.$site['admin_email'].'.');
            }
            $admin = User::updateOrCreate(['username' => $site['admin_username']], [
                'name' => ucfirst($site['admin_username']),
                'email' => $site['admin_email'],
                'password' => $site['admin_password'],
                'role' => 'administrator',
            ]);
        });

        $this->step('Saving site settings', function () use ($site) {
            $forced = [
                'blogname' => $site['title'],
                'blogdescription' => $site['description'] ?? '',
                'admin_email' => $site['admin_email'],
                'timezone_string' => $site['timezone'] ?? config('app.timezone', 'UTC'),
                'discourage_search_engines' => (bool) ($site['discourage_search_engines'] ?? false),
                'active_theme' => $site['theme'] ?? config('cms.default_theme'),
            ];
            foreach ($forced as $name => $value) {
                Option::updateOrCreate(['name' => $name], ['value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'autoload' => true]);
            }
            foreach (static::defaultOptions() as $name => $value) {
                if (! Option::where('name', $name)->exists()) {
                    Option::create(['name' => $name, 'value' => json_encode($value, JSON_UNESCAPED_SLASHES), 'autoload' => true]);
                }
            }
            app(Options::class)->flush();
        });

        if ($site['sample_content'] ?? true) {
            $this->step('Creating sample content', fn () => $this->sampleContent($admin));
        } else {
            Term::firstOrCreate(['taxonomy' => 'category', 'slug' => 'uncategorized'], ['name' => 'Uncategorized']);
        }

        $this->step('Linking public storage for uploads', function () {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
                if (! file_exists(public_path('storage'))) {
                    throw new RuntimeException('Could not create public/storage. Run `php artisan storage:link` manually (media uploads need it).');
                }
            }
        }, critical: false);

        $this->step('Finishing installation', function () {
            File::ensureDirectoryExists(dirname(config('cms.installed_marker')));
            File::put(config('cms.installed_marker'), now()->toIso8601String());
        });

        if (! empty($site['demo'])) {
            $this->step('Activating the Simple Shop demo plugin', function () {
                $plugins = app(PluginManager::class);
                if (! $plugins->get(self::DEMO_PLUGIN)) {
                    throw new RuntimeException('The Simple Shop plugin folder is missing.');
                }
                $plugins->activate(self::DEMO_PLUGIN);
                do_action('cms_demo_content', self::DEMO_PLUGIN);
            }, critical: false);
        }

        do_action('cms_installed', $admin, $site);

        return $admin;
    }

    public static function defaultOptions(): array
    {
        return [
            'blogname' => 'My BoholwebCMS Site',
            'blogdescription' => '',
            'users_can_register' => false,
            'default_role' => 'subscriber',
            'site_language' => 'en',
            'timezone_string' => 'UTC',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i a',
            'start_of_week' => '1',
            'default_category' => 1,
            'default_post_status' => 'draft',
            'default_editor_mode' => 'visual',
            'show_on_front' => 'posts',
            'page_on_front' => 0,
            'page_for_posts' => 0,
            'posts_per_page' => 10,
            'excerpt_on_archives' => 'excerpt',
            'discourage_search_engines' => false,
            'default_comment_status' => true,
            'require_name_email' => true,
            'comment_registration' => false,
            'thread_comments' => true,
            'thread_comments_depth' => 5,
            'comment_moderation' => false,
            'comment_previously_approved' => true,
            'comment_max_links' => 2,
            'show_avatars' => true,
            'thumbnail_size_w' => 150,
            'thumbnail_size_h' => 150,
            'thumbnail_crop' => true,
            'medium_size_w' => 300,
            'medium_size_h' => 300,
            'large_size_w' => 1024,
            'large_size_h' => 1024,
            'uploads_use_yearmonth_folders' => true,
            'permalink_structure' => '/%postname%',
            'category_base' => '',
            'tag_base' => '',
            'active_theme' => config('cms.default_theme'),
            'active_plugins' => [],
            'privacy_policy_page' => 0,
        ];
    }

    /* ------------------------------------------------------------------ */

    protected function step(string $label, callable $fn, bool $critical = true): void
    {
        try {
            $fn();
            $entry = ['step' => $label, 'ok' => true, 'detail' => null];
        } catch (Throwable $e) {
            $entry = ['step' => $label, 'ok' => false, 'detail' => $e->getMessage()];
            $this->log[] = $entry;
            $this->listener && ($this->listener)($entry);
            if ($critical) {
                throw new InstallationFailed($label.': '.$e->getMessage(), $this->log, $e);
            }

            return;
        }

        $this->log[] = $entry;
        $this->listener && ($this->listener)($entry);
    }

    protected function sampleContent(User $admin): void
    {
        $uncategorized = Term::firstOrCreate(['taxonomy' => 'category', 'slug' => 'uncategorized'], ['name' => 'Uncategorized']);

        if (! Post::where('type', 'post')->exists()) {
            $hello = Post::create([
                'type' => 'post', 'status' => 'publish', 'title' => 'Hello world!', 'slug' => 'hello-world',
                'author_id' => $admin->id, 'comment_status' => 'open',
                'content' => '<p>Welcome to BoholwebCMS. This is your first post. Edit or delete it, then start writing!</p>'
                    .'<p>Posts support <strong>shortcodes</strong> — for example this button is rendered by the core <code>[button]</code> shortcode:</p>'
                    .'<p>[button url="/sample-page"]Visit the sample page[/button]</p>',
            ]);
            $hello->terms()->sync([$uncategorized->id]);
            Term::recount([$uncategorized->id]);

            Comment::create([
                'post_id' => $hello->id, 'author_name' => 'A BoholwebCMS Commenter', 'author_email' => 'commenter@example.com',
                'content' => "Hi, this is a comment.\nTo get started with moderating, editing, and deleting comments, visit the Comments screen in the dashboard.",
                'status' => 'approved',
            ]);
        }

        if (! Post::where('type', 'page')->exists()) {
            Post::create([
                'type' => 'page', 'status' => 'publish', 'title' => 'Sample Page', 'slug' => 'sample-page', 'author_id' => $admin->id,
                'comment_status' => 'closed',
                'content' => '<p>This is an example page. It’s different from a blog post because it will stay in one place and will show up in your site navigation (in most themes).</p>'
                    .'<h2>Recent posts</h2><p>[recent_posts count="3" excerpt="true"]</p>',
            ]);

            $privacy = Post::create([
                'type' => 'page', 'status' => 'draft', 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'author_id' => $admin->id,
                'comment_status' => 'closed',
                'content' => '<h2>Who we are</h2><p>Our website address is: '.e(url('/')).'.</p><h2>Comments</h2><p>When visitors leave comments on the site we collect the data shown in the comments form.</p>',
            ]);
            Option::updateOrCreate(['name' => 'privacy_policy_page'], ['value' => json_encode($privacy->id), 'autoload' => true]);
        }
    }
}
