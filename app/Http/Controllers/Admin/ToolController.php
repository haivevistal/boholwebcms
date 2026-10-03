<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Admin\ToolsRegistry;
use App\Cms\Content\PostTypeRegistry;
use App\Cms\Extensions\PluginManager;
use App\Cms\Extensions\ThemeManager;
use App\Cms\Hooks\HookManager;
use App\Cms\Shortcodes\ShortcodeManager;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Option;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Throwable;

class ToolController extends Controller
{
    public function index(ToolsRegistry $tools)
    {
        return Inertia::render('Tools/Index', ['tools' => $tools->resolve()]);
    }

    /* ---------------------------------------------------------------- Export */

    public function export(PostTypeRegistry $types)
    {
        return Inertia::render('Tools/Export', [
            'types' => collect($types->all())->map(fn ($t) => ['name' => $t['name'], 'label' => $t['label'], 'count' => Post::where('type', $t['name'])->count()])->values(),
        ]);
    }

    public function download(Request $request)
    {
        $data = $request->validate([
            'types' => 'array',
            'types.*' => 'string',
            'include_settings' => 'boolean',
            'include_comments' => 'boolean',
        ]);

        $types = $data['types'] ?? [];
        $posts = Post::with(['meta', 'terms:id,taxonomy,slug'])->whereIn('type', $types)->where('status', '!=', 'trash')->orderBy('id')->get();

        $export = [
            'generator' => config('cms.name').' '.cms_version(),
            'site' => url('/'),
            'exported_at' => now()->toIso8601String(),
            'terms' => Term::orderBy('id')->get(['id', 'taxonomy', 'name', 'slug', 'description', 'parent_id']),
            'posts' => $posts->map(fn (Post $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'status' => $p->status,
                'title' => $p->title,
                'slug' => $p->slug,
                'content' => $p->content,
                'excerpt' => $p->excerpt,
                'parent_id' => $p->parent_id,
                'menu_order' => $p->menu_order,
                'template' => $p->template,
                'comment_status' => $p->comment_status,
                'published_at' => $p->published_at?->toIso8601String(),
                'author' => $p->author?->username,
                'terms' => $p->terms->map(fn ($t) => ['taxonomy' => $t->taxonomy, 'slug' => $t->slug])->values(),
                'meta' => $p->meta->pluck('meta_value', 'meta_key'),
            ]),
        ];

        if (! empty($data['include_comments'])) {
            $export['comments'] = Comment::whereIn('post_id', $posts->pluck('id'))->orderBy('id')->get(['id', 'post_id', 'parent_id', 'author_name', 'author_email', 'author_url', 'content', 'status', 'created_at']);
        }
        if (! empty($data['include_settings'])) {
            $skip = ['active_plugins', 'plugin_errors', 'theme_error'];
            $export['options'] = Option::whereNotIn('name', $skip)->get(['name', 'value'])->mapWithKeys(fn ($o) => [$o->name => json_decode($o->value, true)]);
        }

        $export = apply_filters('export_data', $export, $data);

        $filename = str(get_option('blogname', 'site'))->slug().'-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(fn () => print(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /* ---------------------------------------------------------------- Import */

    public function import()
    {
        return Inertia::render('Tools/Import', [
            'importers' => apply_filters('importers', []),
        ]);
    }

    public function runImport(Request $request)
    {
        $request->validate(['file' => 'required|file|max:51200', 'import_settings' => 'boolean']);
        $json = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($json) || ! isset($json['posts'])) {
            return back()->with('error', 'This does not look like a BoholwebCMS export file.');
        }

        $stats = ['posts' => 0, 'terms' => 0, 'comments' => 0, 'skipped' => 0];

        try {
            DB::transaction(function () use ($json, $request, &$stats) {
                $termMap = [];
                foreach ($json['terms'] ?? [] as $t) {
                    $term = Term::firstOrCreate(['taxonomy' => $t['taxonomy'], 'slug' => $t['slug']], ['name' => $t['name'], 'description' => $t['description'] ?? null]);
                    $termMap[$t['id']] = $term->id;
                    $stats['terms'] += $term->wasRecentlyCreated ? 1 : 0;
                }
                foreach ($json['terms'] ?? [] as $t) {
                    if (! empty($t['parent_id']) && isset($termMap[$t['parent_id']])) {
                        Term::whereKey($termMap[$t['id']])->update(['parent_id' => $termMap[$t['parent_id']]]);
                    }
                }

                $postMap = [];
                foreach ($json['posts'] as $p) {
                    if (Post::where('type', $p['type'])->where('slug', $p['slug'])->exists()) {
                        $stats['skipped']++;

                        continue;
                    }
                    $author = User::where('username', $p['author'] ?? '')->value('id') ?? current_user_id();
                    $post = Post::create([
                        'type' => $p['type'], 'status' => $p['status'], 'title' => $p['title'], 'slug' => $p['slug'],
                        'content' => $p['content'], 'excerpt' => $p['excerpt'] ?? null, 'menu_order' => $p['menu_order'] ?? 0,
                        'template' => $p['template'] ?? null, 'comment_status' => $p['comment_status'] ?? 'closed',
                        'published_at' => $p['published_at'] ?? null, 'author_id' => $author,
                    ]);
                    $postMap[$p['id']] = $post->id;
                    foreach ($p['meta'] ?? [] as $k => $v) {
                        $post->meta()->create(['meta_key' => $k, 'meta_value' => $v]);
                    }
                    $ids = collect($p['terms'] ?? [])->map(fn ($t) => Term::where('taxonomy', $t['taxonomy'])->where('slug', $t['slug'])->value('id'))->filter();
                    $post->terms()->sync($ids);
                    $stats['posts']++;
                }
                foreach ($json['posts'] as $p) {
                    if (! empty($p['parent_id']) && isset($postMap[$p['id']], $postMap[$p['parent_id']])) {
                        Post::whereKey($postMap[$p['id']])->update(['parent_id' => $postMap[$p['parent_id']]]);
                    }
                }

                $commentMap = [];
                foreach ($json['comments'] ?? [] as $c) {
                    if (! isset($postMap[$c['post_id']])) {
                        continue;
                    }
                    $comment = Comment::create([
                        'post_id' => $postMap[$c['post_id']], 'author_name' => $c['author_name'], 'author_email' => $c['author_email'],
                        'author_url' => $c['author_url'], 'content' => $c['content'], 'status' => $c['status'],
                    ]);
                    $commentMap[$c['id']] = $comment->id;
                    $stats['comments']++;
                }
                foreach ($json['comments'] ?? [] as $c) {
                    if (! empty($c['parent_id']) && isset($commentMap[$c['id']], $commentMap[$c['parent_id']])) {
                        Comment::whereKey($commentMap[$c['id']])->update(['parent_id' => $commentMap[$c['parent_id']]]);
                    }
                }

                if ($request->boolean('import_settings')) {
                    foreach ($json['options'] ?? [] as $name => $value) {
                        update_option($name, $value);
                    }
                }

                Term::recount(Term::pluck('id')->all());
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        do_action('import_done', $stats);

        return back()->with('success', "Imported {$stats['posts']} posts, {$stats['terms']} terms and {$stats['comments']} comments. Skipped {$stats['skipped']} existing items.");
    }

    /* ----------------------------------------------------------- Site Health */

    public function siteHealth(PluginManager $plugins, ThemeManager $themes)
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $detail, string $severity = 'recommended') use (&$checks) {
            $checks[] = compact('label', 'ok', 'detail', 'severity');
        };

        $add('PHP version', version_compare(PHP_VERSION, '8.2', '>='), 'Running PHP '.PHP_VERSION, 'critical');
        $add('GD extension', extension_loaded('gd'), extension_loaded('gd') ? 'Image sizes can be generated.' : 'Install php-gd to generate thumbnails.');
        $add('Zip extension', class_exists(\ZipArchive::class), class_exists(\ZipArchive::class) ? 'Plugins and themes can be installed from .zip files.' : 'Install php-zip to upload plugins/themes.', 'critical');
        $add('Storage link', file_exists(public_path('storage')), file_exists(public_path('storage')) ? 'public/storage is linked.' : 'Run `php artisan storage:link` so media files are publicly reachable.', 'critical');
        $add('Plugins folder writable', is_writable(config('cms.plugins_path')), config('cms.plugins_path'));
        $add('Themes folder writable', is_writable(config('cms.themes_path')), config('cms.themes_path'));
        $add('Debug mode', ! config('app.debug') || app()->environment('local'), config('app.debug') ? 'APP_DEBUG is on. Turn it off in production.' : 'Debug mode is off.');
        $add('Search engine visibility', ! get_option('discourage_search_engines'), get_option('discourage_search_engines') ? 'Search engines are discouraged from indexing the site.' : 'Search engines may index the site.');
        $add('File editing', config('cms.disallow_file_edit') || app()->environment('local'), config('cms.disallow_file_edit') ? 'Admin file editors are disabled.' : 'Consider CMS_DISALLOW_FILE_EDIT=true on production.');

        $checks = apply_filters('site_status_tests', $checks);

        $dbVersion = null;
        try {
            $dbVersion = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
        }

        $info = [
            'CMS' => [
                'Version' => cms_version(),
                'Site URL' => url('/'),
                'Permalink structure' => get_option('permalink_structure') ?: 'Plain',
                'Active theme' => ($themes->active()['name'] ?? '—').' ('.$themes->activeSlug().')',
                'Active plugins' => count($plugins->activeKeys()),
                'Users' => User::count(),
                'Posts (all types)' => Post::count(),
            ],
            'Server' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Server software' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
                'Memory limit' => ini_get('memory_limit'),
                'Upload max filesize' => ini_get('upload_max_filesize'),
                'Post max size' => ini_get('post_max_size'),
                'Max execution time' => ini_get('max_execution_time'),
            ],
            'Database' => [
                'Driver' => DB::connection()->getDriverName(),
                'Server version' => $dbVersion ?? '—',
                'Database' => DB::connection()->getDatabaseName(),
            ],
            'Environment' => [
                'APP_ENV' => app()->environment(),
                'Debug' => config('app.debug') ? 'on' : 'off',
                'Cache store' => config('cache.default'),
                'Session driver' => config('session.driver'),
                'Queue' => config('queue.default'),
                'Media disk' => config('cms.media.disk').' ('.Storage::disk(config('cms.media.disk'))->path('').')',
            ],
            'Active plugins' => collect($plugins->activeKeys())->mapWithKeys(fn ($k) => [($plugins->get($k)['name'] ?? $k) => ($plugins->get($k)['version'] ?? '')])->all() ?: ['—' => 'none'],
        ];

        return Inertia::render('Tools/SiteHealth', [
            'checks' => $checks,
            'info' => apply_filters('debug_information', $info),
        ]);
    }

    /* -------------------------------------------------------- Hooks inspector */

    public function hooks(HookManager $hooks, ShortcodeManager $shortcodes)
    {
        return Inertia::render('Tools/Hooks', [
            'hooks' => collect($hooks->registered())->map(fn ($count, $name) => ['name' => $name, 'callbacks' => $count])->values(),
            'shortcodes' => $shortcodes->tags(),
            'postTypes' => array_keys(app(PostTypeRegistry::class)->all()),
            'taxonomies' => array_keys(app(\App\Cms\Content\TaxonomyRegistry::class)->all()),
        ]);
    }

    /* ----------------------------------------------------------- Clear cache */

    public function clearCachePage()
    {
        return Inertia::render('Tools/ClearCache');
    }

    public function clearCache()
    {
        foreach (['cache:clear', 'view:clear', 'route:clear', 'config:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
            }
        }
        do_action('cms_cache_cleared');

        return back()->with('success', 'Caches cleared.');
    }
}
