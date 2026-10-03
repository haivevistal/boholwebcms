<?php

namespace App\Cms\Extensions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Themes live in content/themes/{slug} and are described by theme.json:
 *
 * {
 *   "name": "Aurora", "version": "1.0.0", "author": "…", "description": "…",
 *   "screenshot": "screenshot.png",
 *   "script": "dist/theme.js",          // prebuilt React bundle (see theme-kit/)
 *   "style": "dist/theme.css",
 *   "parent": null,                     // child themes: slug of the parent theme
 *   "page_templates": {"full-width": "Full Width"},
 *   "regions": {"sidebar": "Sidebar", "footer": "Footer"},
 *   "menus": {"primary": "Primary Menu"},
 *   "customizer": [ {"id": "colors", "title": "Colors", "controls": [
 *        {"id": "accent_color", "label": "Accent colour", "type": "color", "default": "#4f46e5"} ]} ]
 * }
 *
 * functions.php (optional) is loaded on every request while the theme is
 * active — exactly like WordPress — so themes can add hooks & shortcodes.
 */
class ThemeManager
{
    protected ?array $cache = null;

    protected bool $functionsLoaded = false;

    public function __construct(protected string $path) {}

    public function path(string $append = ''): string
    {
        return rtrim($this->path, '/').($append !== '' ? '/'.ltrim($append, '/') : '');
    }

    public function all(bool $fresh = false): array
    {
        if ($this->cache !== null && ! $fresh) {
            return $this->cache;
        }

        $themes = [];
        foreach (is_dir($this->path) ? scandir($this->path) : [] as $entry) {
            if ($entry[0] === '.' || ! is_dir($this->path($entry))) {
                continue;
            }
            if ($theme = $this->read($entry)) {
                $themes[$entry] = $theme;
            }
        }

        uasort($themes, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $this->cache = $themes;
    }

    public function get(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    public function activeSlug(): string
    {
        $slug = (string) get_option('active_theme', config('cms.default_theme'));

        return $this->get($slug) ? $slug : (array_key_first($this->all()) ?? $slug);
    }

    public function active(): ?array
    {
        return $this->get($this->activeSlug());
    }

    /**
     * Active theme + parent chain, parent first.
     *
     * @return array<int, array>
     */
    public function stack(?string $slug = null): array
    {
        $stack = [];
        $theme = $this->get($slug ?? $this->activeSlug());
        $guard = 0;
        while ($theme && $guard++ < 5) {
            array_unshift($stack, $theme);
            $theme = $theme['parent'] ? $this->get($theme['parent']) : null;
        }

        return $stack;
    }

    public function switch(string $slug): void
    {
        $theme = $this->get($slug) ?? throw new RuntimeException('Theme not found.');
        if ($theme['parent'] && ! $this->get($theme['parent'])) {
            throw new RuntimeException("This child theme requires the parent theme \"{$theme['parent']}\".");
        }

        $old = $this->activeSlug();
        do_action('switch_theme', $slug, $old);
        update_option('active_theme', $slug);
        update_option('theme_switched', $old);
    }

    /**
     * Load functions.php of the active theme (and its parent).
     */
    public function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }
        $this->functionsLoaded = true;

        // Child theme functions run before the parent's, as in WordPress.
        foreach (array_reverse($this->stack()) as $theme) {
            $file = $theme['dir'].'/functions.php';
            if (! is_file($file)) {
                continue;
            }
            try {
                (static function ($__file) {
                    require_once $__file;
                })($file);
            } catch (Throwable $e) {
                Log::error("Theme {$theme['slug']} functions.php failed: {$e->getMessage()}", ['exception' => $e]);
                update_option('theme_error', "{$theme['name']}: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()})");
            }
        }

        if (get_option('theme_switched')) {
            do_action('after_switch_theme', get_option('theme_switched'));
            update_option('theme_switched', null);
        }
    }

    public function delete(string $slug): void
    {
        if (config('cms.disallow_file_mods')) {
            throw new RuntimeException('Theme installation and deletion is disabled (CMS_DISALLOW_FILE_MODS).');
        }
        $theme = $this->get($slug) ?? throw new RuntimeException('Theme not found.');
        if ($slug === $this->activeSlug()) {
            throw new RuntimeException('You cannot delete the active theme.');
        }
        foreach ($this->all() as $other) {
            if ($other['parent'] === $slug && $other['slug'] === $this->activeSlug()) {
                throw new RuntimeException('This theme is the parent of the active theme.');
            }
        }

        do_action('delete_theme', $slug);
        File::deleteDirectory($theme['dir']);
        delete_option('theme_mods_'.$slug);
        $this->cache = null;
        do_action('deleted_theme', $slug);
    }

    public function installZip(string $zipPath, bool $overwrite = false): string
    {
        if (config('cms.disallow_file_mods')) {
            throw new RuntimeException('Theme installation is disabled (CMS_DISALLOW_FILE_MODS).');
        }

        $slug = app(ZipInstaller::class)->install($zipPath, $this->path, function (string $dir) {
            $manifest = $dir.'/theme.json';
            if (! is_file($manifest)) {
                return false;
            }
            $data = json_decode((string) file_get_contents($manifest), true);

            return is_array($data) && ! empty($data['name']);
        }, $overwrite);

        $this->cache = null;
        do_action('installed_theme', $slug);

        return $slug;
    }

    /* ---------------------------------------------------------------------
     | Theme mods (Customizer values)
     * ------------------------------------------------------------------- */

    public function mods(?string $slug = null): array
    {
        $slug ??= $this->activeSlug();
        $defaults = [];
        foreach ($this->stack($slug) as $theme) {
            foreach ($theme['customizer'] as $section) {
                foreach ($section['controls'] ?? [] as $control) {
                    if (($control['setting_type'] ?? 'theme_mod') === 'theme_mod' && isset($control['id'])) {
                        $defaults[$control['id']] = $control['default'] ?? null;
                    }
                }
            }
        }

        $saved = (array) get_option('theme_mods_'.$slug, []);

        return apply_filters('theme_mods', array_merge($defaults, $saved), $slug);
    }

    public function getMod(string $name, mixed $default = null): mixed
    {
        $mods = $this->mods();

        return apply_filters("theme_mod_{$name}", $mods[$name] ?? $default);
    }

    public function setMod(string $name, mixed $value): void
    {
        $slug = $this->activeSlug();
        $mods = (array) get_option('theme_mods_'.$slug, []);
        $mods[$name] = $value;
        update_option('theme_mods_'.$slug, $mods);
    }

    public function removeMod(string $name): void
    {
        $slug = $this->activeSlug();
        $mods = (array) get_option('theme_mods_'.$slug, []);
        unset($mods[$name]);
        update_option('theme_mods_'.$slug, $mods);
    }

    /**
     * Page templates offered by the active theme (+ parent), filterable.
     */
    public function pageTemplates(string $postType = 'page'): array
    {
        $templates = [];
        foreach ($this->stack() as $theme) {
            $templates = array_merge($templates, $theme['page_templates']);
        }

        return apply_filters('theme_page_templates', $templates, $postType);
    }

    public function regions(): array
    {
        $regions = [];
        foreach ($this->stack() as $theme) {
            $regions = array_merge($regions, $theme['regions']);
        }

        return $regions;
    }

    public function assetUrl(array $theme, string $file): string
    {
        return content_url('themes/'.$theme['slug'].'/'.ltrim($file, '/'));
    }

    /* ------------------------------------------------------------------ */

    protected function read(string $slug): ?array
    {
        $dir = $this->path($slug);
        $manifest = $dir.'/theme.json';
        if (! is_file($manifest)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($manifest), true);
        if (! is_array($data) || empty($data['name'])) {
            return null;
        }

        $screenshot = null;
        foreach ([$data['screenshot'] ?? null, 'screenshot.png', 'screenshot.jpg', 'screenshot.webp', 'screenshot.svg'] as $candidate) {
            if ($candidate && is_file($dir.'/'.$candidate)) {
                $screenshot = $candidate;
                break;
            }
        }

        return [
            'slug' => $slug,
            'dir' => $dir,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'version' => $data['version'] ?? '',
            'author' => $data['author'] ?? '',
            'author_uri' => $data['author_uri'] ?? '',
            'uri' => $data['uri'] ?? '',
            'tags' => $data['tags'] ?? [],
            'parent' => $data['parent'] ?? null,
            'screenshot' => $screenshot,
            'script' => $data['script'] ?? 'dist/theme.js',
            'style' => $data['style'] ?? 'dist/theme.css',
            'page_templates' => $data['page_templates'] ?? [],
            'regions' => $data['regions'] ?? [],
            'menus' => $data['menus'] ?? ['primary' => 'Primary Menu'],
            'customizer' => $data['customizer'] ?? [],
            'has_functions' => is_file($dir.'/functions.php'),
        ];
    }
}
