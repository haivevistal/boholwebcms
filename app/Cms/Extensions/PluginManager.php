<?php

namespace App\Cms\Extensions;

use App\Cms\Support\FileHeader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Discovers, loads, activates, deactivates, installs and deletes plugins.
 *
 * A plugin is a folder in content/plugins with a main PHP file that starts
 * with a WordPress-style header ("Plugin Name: ..."). The plugin "key" is
 * "folder/main-file.php" (like WordPress' plugin basename).
 *
 * Optional plugin conventions:
 *   migrations/      run on activation (and by `php artisan migrate` while active)
 *   uninstall.php    executed when the plugin is deleted
 *   assets/          public files: plugin_url(__FILE__, 'assets/app.js')
 */
class PluginManager
{
    /** @var array<string, array>|null */
    protected ?array $cache = null;

    /** @var array<string, string> loaded keys => main file */
    protected array $loaded = [];

    public function __construct(protected string $path) {}

    public function path(string $append = ''): string
    {
        return rtrim($this->path, '/').($append !== '' ? '/'.ltrim($append, '/') : '');
    }

    /**
     * @return array<string, array> key => plugin data
     */
    public function all(bool $fresh = false): array
    {
        if ($this->cache !== null && ! $fresh) {
            return $this->cache;
        }

        $plugins = [];
        if (! is_dir($this->path)) {
            return $this->cache = [];
        }

        foreach (scandir($this->path) as $entry) {
            if ($entry[0] === '.') {
                continue;
            }
            $full = $this->path($entry);

            if (is_file($full) && str_ends_with($entry, '.php')) {
                // Single-file plugin in the plugins root.
                if ($data = $this->readPlugin($full, $entry)) {
                    $plugins[$entry] = $data;
                }

                continue;
            }

            if (! is_dir($full)) {
                continue;
            }

            // Prefer folder/folder.php, then any root PHP file with a header.
            $candidates = [$full.'/'.$entry.'.php', $full.'/plugin.php'];
            foreach (glob($full.'/*.php') ?: [] as $file) {
                $candidates[] = $file;
            }
            foreach (array_unique($candidates) as $file) {
                if (! is_file($file)) {
                    continue;
                }
                $key = $entry.'/'.basename($file);
                if ($data = $this->readPlugin($file, $key)) {
                    $plugins[$key] = $data;
                    break;
                }
            }
        }

        uasort($plugins, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $this->cache = $plugins;
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public function activeKeys(): array
    {
        return array_values(array_filter((array) get_option('active_plugins', []), 'is_string'));
    }

    public function isActive(string $key): bool
    {
        return in_array($key, $this->activeKeys(), true);
    }

    /**
     * Include every active plugin. Broken plugins are deactivated instead of
     * taking the site down, and the error is shown in the admin.
     */
    public function loadActive(): void
    {
        foreach ($this->activeKeys() as $key) {
            $plugin = $this->get($key);
            if (! $plugin) {
                $this->setActive(array_diff($this->activeKeys(), [$key]));
                $this->recordError($key, 'Plugin file does not exist. It has been deactivated.');

                continue;
            }

            try {
                $this->includeFile($key, $plugin['file']);
                do_action('plugin_loaded', $key, $plugin);
            } catch (Throwable $e) {
                Log::error("Plugin {$key} failed to load: {$e->getMessage()}", ['exception' => $e]);
                $this->setActive(array_diff($this->activeKeys(), [$key]));
                $this->recordError($key, 'Deactivated because it caused an error: '.$e->getMessage().' in '.basename($e->getFile()).':'.$e->getLine());
            }
        }
    }

    /**
     * Migration folders of active plugins (registered with the migrator).
     *
     * @return array<int, string>
     */
    public function activeMigrationPaths(): array
    {
        $paths = [];
        foreach ($this->activeKeys() as $key) {
            if (($plugin = $this->get($key)) && is_dir($plugin['dir'].'/migrations')) {
                $paths[] = $plugin['dir'].'/migrations';
            }
        }

        return $paths;
    }

    public function activate(string $key): void
    {
        $plugin = $this->get($key) ?? throw new RuntimeException('Plugin not found.');

        if ($this->isActive($key)) {
            return;
        }

        if ($plugin['requires_php'] && version_compare(PHP_VERSION, $plugin['requires_php'], '<')) {
            throw new RuntimeException("{$plugin['name']} requires PHP {$plugin['requires_php']} or newer.");
        }

        foreach ($plugin['requires_plugins'] as $slug) {
            $dependency = collect($this->all())->first(fn ($p) => $p['slug'] === $slug);
            if (! $dependency || ! $this->isActive($dependency['key'])) {
                throw new RuntimeException("{$plugin['name']} requires the plugin \"{$slug}\" to be installed and active.");
            }
        }

        // Sandbox: load the file and run its activation hook; if anything
        // throws, the plugin stays inactive.
        try {
            $this->includeFile($key, $plugin['file']);

            if (is_dir($plugin['dir'].'/migrations')) {
                Artisan::call('migrate', ['--path' => $plugin['dir'].'/migrations', '--realpath' => true, '--force' => true]);
            }

            do_action('activate_plugin', $key);
            do_action("activate_{$key}");
        } catch (Throwable $e) {
            throw new RuntimeException("Plugin could not be activated: {$e->getMessage()} ({$this->relative($e->getFile())}:{$e->getLine()})", 0, $e);
        }

        $this->setActive(array_merge($this->activeKeys(), [$key]));
        $this->clearError($key);

        do_action('activated_plugin', $key);
    }

    public function deactivate(string $key): void
    {
        if (! $this->isActive($key)) {
            return;
        }

        try {
            do_action('deactivate_plugin', $key);
            do_action("deactivate_{$key}");
        } catch (Throwable $e) {
            Log::warning("Deactivation hook of {$key} failed: {$e->getMessage()}");
        }

        $this->setActive(array_diff($this->activeKeys(), [$key]));

        do_action('deactivated_plugin', $key);
    }

    public function delete(string $key): void
    {
        if (config('cms.disallow_file_mods')) {
            throw new RuntimeException('Plugin installation and deletion is disabled (CMS_DISALLOW_FILE_MODS).');
        }

        $plugin = $this->get($key) ?? throw new RuntimeException('Plugin not found.');
        if ($this->isActive($key)) {
            throw new RuntimeException('Deactivate the plugin before deleting it.');
        }

        do_action('delete_plugin', $key);

        try {
            $uninstall = $plugin['dir'].'/uninstall.php';
            if ($plugin['is_folder'] && is_file($uninstall)) {
                if (! defined('CMS_UNINSTALL_PLUGIN')) {
                    define('CMS_UNINSTALL_PLUGIN', $key);
                }
                (static function ($__file) {
                    require $__file;
                })($uninstall);
            } else {
                $this->includeFile($key, $plugin['file']);
                do_action("uninstall_{$key}");
            }
        } catch (Throwable $e) {
            Log::warning("Uninstall routine of {$key} failed: {$e->getMessage()}");
        }

        if ($plugin['is_folder']) {
            File::deleteDirectory($plugin['dir']);
        } else {
            File::delete($plugin['file']);
        }

        $this->cache = null;
        do_action('deleted_plugin', $key);
    }

    public function installZip(string $zipPath, bool $overwrite = false): string
    {
        if (config('cms.disallow_file_mods')) {
            throw new RuntimeException('Plugin installation is disabled (CMS_DISALLOW_FILE_MODS).');
        }

        $folder = app(ZipInstaller::class)->install($zipPath, $this->path, function (string $dir) {
            foreach (glob($dir.'/*.php') ?: [] as $file) {
                if (FileHeader::read($file, ['name' => 'Plugin Name'])['name'] !== '') {
                    return true;
                }
            }

            return false;
        }, $overwrite);

        $this->cache = null;
        $key = collect($this->all())->first(fn ($p) => $p['slug'] === $folder)['key'] ?? $folder;

        do_action('installed_plugin', $key);

        return $key;
    }

    /**
     * Generate a ready-to-edit plugin skeleton.
     */
    public function scaffold(string $name, string $description = '', string $author = ''): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            throw new RuntimeException('Please enter a plugin name.');
        }
        $dir = $this->path($slug);
        if (is_dir($dir)) {
            throw new RuntimeException("A plugin folder named \"{$slug}\" already exists.");
        }

        $fn = Str::snake(Str::camel($slug));
        $stub = <<<PHP
<?php
/**
 * Plugin Name: {$name}
 * Description: {$description}
 * Version: 1.0.0
 * Author: {$author}
 * Requires CMS: 1.0
 */

defined('CMS_LOADED') || exit;

// Runs once when the plugin is activated.
register_activation_hook(__FILE__, function () {
    add_option('{$fn}_greeting', 'Hello from {$name}!');
});

// Example shortcode: [{$slug}]
add_shortcode('{$slug}', function (array \$atts, ?string \$content = null) {
    \$atts = shortcode_atts(['greeting' => get_option('{$fn}_greeting')], \$atts, '{$slug}');

    return '<div class="{$slug}">'.e(\$atts['greeting']).'</div>';
});

// Example admin page under Tools.
add_action('admin_menu', function () {
    add_submenu_page('tools', '{$name}', '{$name}', 'manage_options', '{$slug}', function () {
        return '<p>Edit <code>content/plugins/{$slug}/{$slug}.php</code> to build your plugin.</p>';
    });
});

PHP;

        File::ensureDirectoryExists($dir.'/assets');
        File::put($dir.'/'.$slug.'.php', $stub);
        File::put($dir.'/assets/.gitkeep', '');
        File::put($dir.'/readme.md', "# {$name}\n\n{$description}\n");

        $this->cache = null;

        return $slug.'/'.$slug.'.php';
    }

    public function errors(): array
    {
        return (array) get_option('plugin_errors', []);
    }

    public function clearErrors(): void
    {
        update_option('plugin_errors', []);
    }

    /**
     * Remember which file belongs to which plugin key, so
     * register_activation_hook(__FILE__) can find it.
     */
    public function keyForFile(string $file): ?string
    {
        $file = realpath($file) ?: $file;
        foreach ($this->all() as $key => $plugin) {
            if ((realpath($plugin['file']) ?: $plugin['file']) === $file) {
                return $key;
            }
        }

        // A file deeper inside a plugin folder.
        $root = realpath($this->path) ?: $this->path;
        if (str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            $folder = explode(DIRECTORY_SEPARATOR, substr($file, strlen($root) + 1))[0];
            foreach ($this->all() as $key => $plugin) {
                if ($plugin['slug'] === $folder) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function isLoaded(string $key): bool
    {
        return isset($this->loaded[$key]);
    }

    /* ------------------------------------------------------------------ */

    protected function includeFile(string $key, string $file): void
    {
        if (isset($this->loaded[$key])) {
            return;
        }
        $this->loaded[$key] = $file;

        // Isolated scope so plugin variables don't leak.
        (static function ($__file) {
            require_once $__file;
        })($file);
    }

    protected function readPlugin(string $file, string $key): ?array
    {
        $h = FileHeader::read($file, FileHeader::PLUGIN_HEADERS);
        if ($h['name'] === '') {
            return null;
        }

        $isFolder = str_contains($key, '/');
        $dir = $isFolder ? dirname($file) : $this->path;
        $slug = $isFolder ? explode('/', $key)[0] : basename($key, '.php');

        return [
            'key' => $key,
            'slug' => $slug,
            'file' => $file,
            'dir' => $dir,
            'is_folder' => $isFolder,
            'name' => $h['name'],
            'description' => $h['description'],
            'version' => $h['version'],
            'author' => $h['author'],
            'author_uri' => $h['author_uri'],
            'uri' => $h['uri'],
            'requires' => $h['requires'],
            'requires_php' => $h['requires_php'],
            'requires_plugins' => array_values(array_filter(array_map('trim', explode(',', $h['requires_plugins'])))),
            'license' => $h['license'],
            'has_migrations' => $isFolder && is_dir($dir.'/migrations'),
        ];
    }

    protected function setActive(array $keys): void
    {
        $keys = array_values(array_unique($keys));
        sort($keys);
        update_option('active_plugins', $keys);
    }

    protected function recordError(string $key, string $message): void
    {
        $errors = $this->errors();
        $errors[$key] = $message;
        update_option('plugin_errors', $errors);
    }

    protected function clearError(string $key): void
    {
        $errors = $this->errors();
        if (isset($errors[$key])) {
            unset($errors[$key]);
            update_option('plugin_errors', $errors);
        }
    }

    protected function relative(string $file): string
    {
        return str_replace(base_path().'/', '', $file);
    }
}
