<?php

namespace App\Console\Commands;

use App\Cms\Extensions\PluginManager;
use Illuminate\Console\Command;
use Throwable;

/**
 *   php artisan cms:plugin list
 *   php artisan cms:plugin activate simple-shop
 *   php artisan cms:plugin deactivate simple-shop/simple-shop.php
 *   php artisan cms:plugin make "My Plugin"
 */
class PluginCommand extends Command
{
    protected $signature = 'cms:plugin {action=list : list|activate|deactivate|delete|make} {plugin? : plugin folder, key or (for make) name}
        {--demo-content : Ask the plugin to create demo content after activation (fires "cms_demo_content")}';

    protected $description = 'Manage CMS plugins from the command line';

    public function handle(PluginManager $plugins): int
    {
        if (! cms_installed()) {
            $this->error('Run `php artisan cms:install` first.');

            return self::FAILURE;
        }

        $action = $this->argument('action');

        if ($action === 'list') {
            $this->table(['Key', 'Name', 'Version', 'Status'], collect($plugins->all())->map(fn ($p) => [
                $p['key'], $p['name'], $p['version'], $plugins->isActive($p['key']) ? '<info>active</info>' : 'inactive',
            ])->values());

            return self::SUCCESS;
        }

        if ($action === 'make') {
            $key = $plugins->scaffold((string) $this->argument('plugin'));
            $this->components->info("Created content/plugins/{$key}");

            return self::SUCCESS;
        }

        $key = $this->resolveKey($plugins, (string) $this->argument('plugin'));
        if (! $key) {
            $this->error('Plugin not found.');

            return self::FAILURE;
        }

        try {
            match ($action) {
                'activate' => $plugins->activate($key),
                'deactivate' => $plugins->deactivate($key),
                'delete' => $plugins->delete($key),
                default => throw new \InvalidArgumentException("Unknown action {$action}"),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($action === 'activate' && $this->option('demo-content')) {
            do_action('cms_demo_content', $key);
        }

        $this->components->info(ucfirst($action).'d '.$key);

        return self::SUCCESS;
    }

    protected function resolveKey(PluginManager $plugins, string $input): ?string
    {
        if ($plugins->get($input)) {
            return $input;
        }
        foreach ($plugins->all() as $key => $plugin) {
            if ($plugin['slug'] === $input) {
                return $key;
            }
        }

        return null;
    }
}
