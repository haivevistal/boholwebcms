<?php

namespace App\Console\Commands;

use App\Cms\Support\Capabilities;
use App\Models\Comment;
use App\Models\Option;
use App\Models\Post;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The "famous 5-minute install":  php artisan cms:install
 */
class InstallCommand extends Command
{
    protected $signature = 'cms:install
        {--title= : Site title}
        {--username= : Admin username}
        {--email= : Admin email}
        {--password= : Admin password}
        {--fresh : Drop all tables first (destroys data)}
        {--demo : Activate the Simple Shop demo plugin with sample products}
        {--force : Run without confirmation prompts}';

    protected $description = 'Install BoholwebCMS: run migrations, create the admin account and default content';

    public function handle(): int
    {
        $this->components->info('Installing '.config('cms.name').' '.cms_version());

        if (! config('app.key')) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->task('Application key generated');
        }

        $this->ensureSqliteDatabase();

        if (is_file(config('cms.installed_marker')) && ! $this->option('fresh') && ! $this->option('force')) {
            if (! $this->confirm('BoholwebCMS already looks installed. Continue anyway?', false)) {
                return self::SUCCESS;
            }
        }

        $interactive = $this->input->isInteractive() && ! $this->option('force');
        $title = $this->option('title') ?: ($interactive ? $this->ask('Site title', 'My BoholwebCMS Site') : 'My BoholwebCMS Site');
        $username = $this->option('username') ?: ($interactive ? $this->ask('Admin username', 'admin') : 'admin');
        $email = $this->option('email') ?: ($interactive ? $this->ask('Admin email', 'admin@example.com') : 'admin@example.com');
        $password = $this->option('password') ?: ($interactive ? $this->secret('Admin password (leave empty to generate one)') : null);
        $generated = false;
        if (! $password) {
            $password = str()->password(16, symbols: false);
            $generated = true;
        }

        $this->components->task('Running migrations', function () {
            Artisan::call($this->option('fresh') ? 'migrate:fresh' : 'migrate', ['--force' => true]);
        });

        $this->components->task('Creating roles', function () {
            foreach (Capabilities::DEFAULT_ROLES as $slug => $role) {
                Role::updateOrCreate(['slug' => $slug], ['name' => $role['name'], 'capabilities' => $role['capabilities']]);
            }
        });

        $admin = null;
        $this->components->task('Creating administrator', function () use (&$admin, $username, $email, $password) {
            $admin = User::updateOrCreate(['username' => $username], [
                'name' => ucfirst($username),
                'email' => $email,
                'password' => $password,
                'role' => 'administrator',
            ]);
        });

        $this->components->task('Saving default settings', function () use ($title, $email) {
            foreach ($this->defaultOptions($title, $email) as $name => $value) {
                if (! Option::where('name', $name)->exists()) {
                    Option::create(['name' => $name, 'value' => json_encode($value, JSON_UNESCAPED_SLASHES), 'autoload' => true]);
                }
            }
        });

        $this->components->task('Creating sample content', fn () => $this->sampleContent($admin));

        $this->components->task('Linking public storage', function () {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        });

        File::ensureDirectoryExists(dirname(config('cms.installed_marker')));
        File::put(config('cms.installed_marker'), now()->toIso8601String());

        if ($this->option('demo')) {
            $this->components->task('Activating Simple Shop demo plugin', function () {
                $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'cms:plugin', 'activate', 'simple-shop', '--demo-content']);
                if (! $result->successful()) {
                    $this->warn($result->errorOutput() ?: $result->output());
                }
            });
        }

        $this->newLine();
        $this->components->info('BoholwebCMS is installed!');
        $this->components->twoColumnDetail('Site', url('/'));
        $this->components->twoColumnDetail('Admin', url('/admin'));
        $this->components->twoColumnDetail('Username', $username);
        $this->components->twoColumnDetail('Password', $generated ? $password.' (generated — save it now)' : '(the one you entered)');

        return self::SUCCESS;
    }

    protected function ensureSqliteDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }
        $path = config('database.connections.sqlite.database');
        if ($path && $path !== ':memory:' && ! file_exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            touch($path);
            $this->components->task('Created SQLite database at '.str_replace(base_path().'/', '', $path));
        }
    }

    protected function defaultOptions(string $title, string $email): array
    {
        return [
            'blogname' => $title,
            'blogdescription' => 'Just another BoholwebCMS site',
            'admin_email' => $email,
            'users_can_register' => false,
            'default_role' => 'subscriber',
            'site_language' => 'en',
            'timezone_string' => config('app.timezone', 'UTC'),
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
