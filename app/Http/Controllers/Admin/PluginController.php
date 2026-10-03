<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Extensions\PluginManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class PluginController extends Controller
{
    public function __construct(protected PluginManager $plugins) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $all = collect($this->plugins->all(true));
        $active = $this->plugins->activeKeys();

        $list = $all->map(fn ($p) => [
            'key' => $p['key'],
            'slug' => $p['slug'],
            'name' => $p['name'],
            'description' => $p['description'],
            'version' => $p['version'],
            'author' => $p['author'],
            'author_uri' => $p['author_uri'],
            'uri' => $p['uri'],
            'requires_plugins' => $p['requires_plugins'],
            'active' => in_array($p['key'], $active, true),
            'action_links' => in_array($p['key'], $active, true) ? apply_filters("plugin_action_links_{$p['key']}", [], $p) : [],
        ]);

        if ($s = trim((string) $request->query('s'))) {
            $list = $list->filter(fn ($p) => stripos($p['name'].' '.$p['description'], $s) !== false);
        }

        $counts = [
            'all' => $all->count(),
            'active' => $all->filter(fn ($p) => in_array($p['key'], $active, true))->count(),
            'inactive' => $all->reject(fn ($p) => in_array($p['key'], $active, true))->count(),
        ];

        if ($status === 'active') {
            $list = $list->where('active', true);
        } elseif ($status === 'inactive') {
            $list = $list->where('active', false);
        }

        return Inertia::render('Plugins/Index', [
            'plugins' => $list->values(),
            'counts' => $counts,
            'filters' => ['status' => $status, 's' => $request->query('s')],
            'can' => [
                'install' => current_user_can('install_plugins') && ! config('cms.disallow_file_mods'),
                'delete' => current_user_can('delete_plugins') && ! config('cms.disallow_file_mods'),
                'edit' => current_user_can('edit_plugins') && ! config('cms.disallow_file_edit'),
            ],
        ]);
    }

    public function add()
    {
        abort_if(config('cms.disallow_file_mods'), 403, 'Plugin installation is disabled.');

        return Inertia::render('Plugins/Add', [
            'maxUploadMb' => 50,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate(['package' => 'required|file|mimes:zip|max:51200', 'overwrite' => 'nullable|boolean', 'activate' => 'nullable|boolean']);

        try {
            $key = $this->plugins->installZip($request->file('package')->getRealPath(), $request->boolean('overwrite'));
            if ($request->boolean('activate')) {
                $this->plugins->activate($key);
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Installation failed: '.$e->getMessage());
        }

        return redirect()->route('admin.plugins.index')->with('success', 'Plugin installed'.($request->boolean('activate') ? ' and activated.' : '. Activate it to start using it.'));
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:100',
        ]);

        try {
            $key = $this->plugins->scaffold($data['name'], $data['description'] ?? '', $data['author'] ?? (current_user()?->name ?? ''));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.plugin-editor', ['plugin' => $key])->with('success', 'Plugin created. It is inactive until you activate it.');
    }

    public function activate(Request $request)
    {
        $key = $request->validate(['plugin' => 'required|string'])['plugin'];

        try {
            $this->plugins->activate($key);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        // Full reload so newly registered menus, scripts & routes are picked up.
        return Inertia::location(route('admin.plugins.index', ['activated' => 1]));
    }

    public function deactivate(Request $request)
    {
        $key = $request->validate(['plugin' => 'required|string'])['plugin'];
        $this->plugins->deactivate($key);

        return Inertia::location(route('admin.plugins.index', ['deactivated' => 1]));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['action' => 'required|in:activate,deactivate,delete', 'plugins' => 'required|array', 'plugins.*' => 'string']);
        $errors = [];

        foreach ($data['plugins'] as $key) {
            try {
                match ($data['action']) {
                    'activate' => $this->plugins->activate($key),
                    'deactivate' => $this->plugins->deactivate($key),
                    'delete' => current_user_can('delete_plugins') ? $this->plugins->delete($key) : null,
                };
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            session()->flash('error', implode(' ', $errors));
        }

        return Inertia::location(route('admin.plugins.index'));
    }

    public function destroy(Request $request)
    {
        $key = $request->validate(['plugin' => 'required|string'])['plugin'];

        try {
            $this->plugins->delete($key);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Plugin deleted.');
    }

    public function dismissErrors()
    {
        $this->plugins->clearErrors();

        return back();
    }
}
