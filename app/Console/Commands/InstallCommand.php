<?php

namespace App\Console\Commands;

use App\Cms\Install\InstallationFailed;
use App\Cms\Install\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Command-line installer (the web installer lives at /install):
 *   php artisan cms:install
 *   php artisan cms:install --title="My Site" --username=admin --email=me@site.com --password=secret --demo --force
 *
 * Uses the database configured in .env.
 */
class InstallCommand extends Command
{
    protected $signature = 'cms:install
        {--title= : Site title}
        {--description= : Site tagline}
        {--username= : Admin username}
        {--email= : Admin email}
        {--password= : Admin password}
        {--theme= : Theme to activate (default: aurora)}
        {--no-sample : Skip the sample post, page and comment}
        {--fresh : Drop all tables first (destroys data)}
        {--demo : Activate the Simple Shop demo plugin with sample products}
        {--force : Run without confirmation prompts}';

    protected $description = 'Install BoholwebCMS: run migrations, create the admin account and default content';

    public function handle(Installer $installer): int
    {
        $this->components->info('Installing '.config('cms.name').' '.cms_version());

        if (! config('app.key')) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->twoColumnDetail('Application key', '<fg=green>generated</>');
        }

        $this->ensureSqliteDatabase();

        if (is_file(config('cms.installed_marker')) && ! $this->option('fresh') && ! $this->option('force')
            && ! $this->confirm('BoholwebCMS already looks installed. Continue anyway?', false)) {
            return self::SUCCESS;
        }

        $interactive = $this->input->isInteractive() && ! $this->option('force');
        $ask = fn (string $option, string $question, string $default) => $this->option($option) ?: ($interactive ? $this->ask($question, $default) : $default);

        $site = [
            'title' => $ask('title', 'Site title', 'My BoholwebCMS Site'),
            'description' => $this->option('description') ?? '',
            'admin_username' => $ask('username', 'Admin username', 'admin'),
            'admin_email' => $ask('email', 'Admin email', 'admin@example.com'),
            'admin_password' => $this->option('password') ?: ($interactive ? (string) $this->secret('Admin password (leave empty to generate one)') : ''),
            'theme' => $this->option('theme') ?: config('cms.default_theme'),
            'timezone' => config('app.timezone', 'UTC'),
            'sample_content' => ! $this->option('no-sample'),
            'demo' => (bool) $this->option('demo'),
            'fresh' => (bool) $this->option('fresh'),
        ];

        $generated = false;
        if ($site['admin_password'] === '') {
            $site['admin_password'] = str()->password(16, symbols: false);
            $generated = true;
        }

        $installer->onStep(function (array $entry) {
            $this->components->twoColumnDetail($entry['step'], $entry['ok'] ? '<fg=green;options=bold>DONE</>' : '<fg=yellow;options=bold>FAILED</>');
            if (! $entry['ok'] && $entry['detail']) {
                $this->components->warn($entry['detail']);
            }
        });

        try {
            $installer->install($site);
        } catch (InstallationFailed $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('BoholwebCMS is installed!');
        $this->components->twoColumnDetail('Site', url('/'));
        $this->components->twoColumnDetail('Admin', url('/admin'));
        $this->components->twoColumnDetail('Username', $site['admin_username']);
        $this->components->twoColumnDetail('Password', $generated ? $site['admin_password'].' (generated — save it now)' : '(the one you entered)');

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
            $this->components->twoColumnDetail('SQLite database', '<fg=green>created</>');
        }
    }
}
