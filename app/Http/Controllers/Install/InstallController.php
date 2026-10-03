<?php

namespace App\Http\Controllers\Install;

use App\Cms\Extensions\ThemeManager;
use App\Cms\Install\DatabaseProbe;
use App\Cms\Install\InstallationFailed;
use App\Cms\Install\Installer;
use App\Cms\Install\Requirements;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

/**
 * The web installer (the wp-admin/install.php equivalent):
 *   1. requirements  2. database  3. site details  4. install
 * Only reachable until the site is installed (see EnsureNotInstalled).
 */
class InstallController extends Controller
{
    public function show(Request $request, ThemeManager $themes)
    {
        $env = new \App\Cms\Install\EnvEditor(base_path('.env'));

        return Inertia::render('Install/Wizard', [
            'requirements' => (new Requirements(base_path()))->check(),
            'drivers' => collect(DatabaseProbe::DRIVERS)->map(fn ($d, $key) => [
                'value' => $key,
                'label' => $d['label'],
                'port' => $d['port'],
                'available' => extension_loaded($d['pdo']),
                'extension' => $d['pdo'],
            ])->values(),
            'defaults' => [
                'url' => rtrim($request->root(), '/'),
                'sqlite_path' => 'database/database.sqlite',
                'timezone' => config('app.timezone', 'UTC'),
                // Pre-fill from an existing .env (e.g. credentials added by a hosting panel).
                'db' => [
                    'driver' => in_array($env->get('DB_CONNECTION'), array_keys(DatabaseProbe::DRIVERS), true) && $env->get('DB_CONNECTION') !== 'sqlite' ? $env->get('DB_CONNECTION') : 'mysql',
                    'host' => $env->get('DB_HOST') ?: 'localhost',
                    'database' => $env->get('DB_DATABASE') && ! str_contains((string) $env->get('DB_DATABASE'), '/') ? $env->get('DB_DATABASE') : '',
                    'username' => $env->get('DB_USERNAME') ?: '',
                    'prefix' => $env->get('DB_PREFIX') ?: 'bw_',
                ],
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'themes' => collect($themes->all())->filter(fn ($t) => ! $t['parent'])->map(fn ($t) => [
                'slug' => $t['slug'],
                'name' => $t['name'],
                'screenshot' => $t['screenshot'] ? $themes->assetUrl($t, $t['screenshot']) : null,
            ])->values(),
            'demoAvailable' => is_dir(config('cms.plugins_path').'/simple-shop'),
            'cms' => ['name' => config('cms.name'), 'version' => cms_version()],
        ]);
    }

    /**
     * Step 2: test the database credentials (AJAX).
     */
    public function checkDatabase(Request $request, DatabaseProbe $probe)
    {
        $data = $this->validateDatabase($request);

        return response()->json($probe->test(Installer::normalizeDatabase($data)));
    }

    /**
     * Step 4: run the installation (AJAX). Returns the step log.
     */
    public function run(Request $request, Installer $installer, DatabaseProbe $probe)
    {
        @set_time_limit(300);

        $dbInput = $this->validateDatabase($request);
        $site = $request->validate([
            'site.title' => 'required|string|max:190',
            'site.description' => 'nullable|string|max:255',
            'site.url' => 'required|url|max:255',
            'site.admin_username' => 'required|alpha_dash|min:3|max:60',
            'site.admin_email' => 'required|email|max:190',
            'site.admin_password' => 'required|string|min:8|max:190',
            'site.timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'site.theme' => 'nullable|string|max:100',
            'site.discourage_search_engines' => 'boolean',
            'site.sample_content' => 'boolean',
            'site.demo' => 'boolean',
            'site.production' => 'boolean',
        ], [], [
            'site.title' => 'site title', 'site.url' => 'site address', 'site.admin_username' => 'username',
            'site.admin_email' => 'email', 'site.admin_password' => 'password',
        ])['site'];

        $db = Installer::normalizeDatabase($dbInput);

        $check = $probe->test($db);
        $create = $request->boolean('database.create');
        if (! $check['ok'] && ! ($check['can_create'] && $create)) {
            return response()->json(['success' => false, 'message' => $check['message'], 'log' => []], 422);
        }

        try {
            $installer->prepareDatabase($db, $create && ! $check['database_exists']);
            $installer->writeEnvironment($db, $site);
            $installer->useDatabase($db);
            $installer->install($site + ['fresh' => false]);
        } catch (InstallationFailed $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'log' => $e->log], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'log' => $installer->log()], 500);
        }

        return response()->json([
            'success' => true,
            'log' => $installer->log(),
            'login_url' => rtrim($site['url'], '/').'/login',
            'site_url' => rtrim($site['url'], '/').'/',
            'username' => $site['admin_username'],
        ]);
    }

    protected function validateDatabase(Request $request): array
    {
        $driver = $request->input('database.driver');

        return $request->validate([
            'database.driver' => ['required', Rule::in(array_keys(DatabaseProbe::DRIVERS))],
            'database.host' => $driver === 'sqlite' ? 'nullable' : 'required|string|max:255',
            'database.port' => 'nullable|integer|between:1,65535',
            'database.database' => $driver === 'sqlite' ? 'nullable|string|max:500' : ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'database.username' => $driver === 'sqlite' ? 'nullable' : 'required|string|max:190',
            'database.password' => 'nullable|string|max:190',
            'database.prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_]*$/'],
            'database.create' => 'boolean',
        ], [
            'database.database.regex' => 'Database names may only contain letters, numbers, underscores and dashes.',
            'database.prefix.regex' => 'The table prefix may only contain letters, numbers and underscores.',
        ], [
            'database.host' => 'database host', 'database.database' => 'database name', 'database.username' => 'username',
        ])['database'];
    }
}
