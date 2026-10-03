<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Extensions\FileEditor;
use App\Cms\Extensions\PluginManager;
use App\Cms\Extensions\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * Appearance → Theme File Editor and Plugins → Plugin File Editor.
 */
class FileEditorController extends Controller
{
    public function __construct(protected FileEditor $editor) {}

    public function theme(Request $request, ThemeManager $themes)
    {
        abort_if(config('cms.disallow_file_edit'), 403, 'File editing is disabled.');

        $all = $themes->all();
        $slug = $request->query('theme', $themes->activeSlug());
        abort_unless(isset($all[$slug]), 404);
        $theme = $all[$slug];

        $files = $this->editor->files($theme['dir']);
        $file = $request->query('file', $this->defaultFile($files, ['theme.json', 'functions.php']));

        return Inertia::render('Editors/FileEditor', [
            'kind' => 'theme',
            'title' => 'Edit Themes',
            'packages' => collect($all)->map(fn ($t) => ['value' => $t['slug'], 'label' => $t['name']])->values(),
            'package' => $slug,
            'packageName' => $theme['name'],
            'isActive' => $slug === $themes->activeSlug(),
            'files' => $files,
            'file' => $file,
            'contents' => $file ? $this->safeRead($theme['dir'], $file) : '',
            'saveUrl' => route('admin.theme-editor.save'),
            'notice' => 'Changes to dist/ files, theme.json and functions.php apply immediately. Changes to React sources in src/ require rebuilding the theme bundle (see theme-kit/README.md).',
        ]);
    }

    public function saveTheme(Request $request, ThemeManager $themes)
    {
        $data = $request->validate(['package' => 'required|string', 'file' => 'required|string', 'contents' => 'present|nullable|string']);
        $theme = $themes->get($data['package']) ?? abort(404);

        return $this->save($theme['dir'], $data, 'theme');
    }

    public function plugin(Request $request, PluginManager $plugins)
    {
        abort_if(config('cms.disallow_file_edit'), 403, 'File editing is disabled.');

        $all = $plugins->all();
        abort_if(empty($all), 404, 'No plugins installed.');

        $key = $request->query('plugin', array_key_first($all));
        abort_unless(isset($all[$key]), 404);
        $plugin = $all[$key];

        $root = $plugin['is_folder'] ? $plugin['dir'] : dirname($plugin['file']);
        $files = $plugin['is_folder'] ? $this->editor->files($root) : [['path' => basename($plugin['file']), 'size' => filesize($plugin['file'])]];
        $file = $request->query('file', basename($plugin['file']));

        return Inertia::render('Editors/FileEditor', [
            'kind' => 'plugin',
            'title' => 'Edit Plugins',
            'packages' => collect($all)->map(fn ($p) => ['value' => $p['key'], 'label' => $p['name']])->values(),
            'package' => $key,
            'packageName' => $plugin['name'],
            'isActive' => $plugins->isActive($key),
            'files' => $files,
            'file' => $file,
            'contents' => $this->safeRead($root, $file),
            'saveUrl' => route('admin.plugin-editor.save'),
            'notice' => 'Editing an active plugin takes effect immediately. PHP files are syntax-checked before saving, and a backup of each file is stored in storage/app/file-editor-backups.',
        ]);
    }

    public function savePlugin(Request $request, PluginManager $plugins)
    {
        $data = $request->validate(['package' => 'required|string', 'file' => 'required|string', 'contents' => 'present|nullable|string']);
        $plugin = $plugins->get($data['package']) ?? abort(404);
        $root = $plugin['is_folder'] ? $plugin['dir'] : dirname($plugin['file']);

        return $this->save($root, $data, 'plugin');
    }

    protected function save(string $root, array $data, string $kind)
    {
        try {
            $this->editor->write($root, $data['file'], (string) ($data['contents'] ?? ''));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        do_action("{$kind}_file_edited", $data['package'], $data['file']);

        return back()->with('success', 'File edited successfully.');
    }

    protected function safeRead(string $root, ?string $file): string
    {
        if (! $file) {
            return '';
        }
        try {
            return $this->editor->read($root, $file);
        } catch (Throwable) {
            abort(404, 'File not found or not editable.');
        }
    }

    protected function defaultFile(array $files, array $preferred): ?string
    {
        $paths = array_column($files, 'path');
        foreach ($preferred as $p) {
            if (in_array($p, $paths, true)) {
                return $p;
            }
        }

        return $paths[0] ?? null;
    }
}
