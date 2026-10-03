<?php

namespace App\Console\Commands;

use App\Cms\Extensions\ThemeManager;
use Illuminate\Console\Command;

/**
 *   php artisan cms:theme list
 *   php artisan cms:theme activate minimal
 */
class ThemeCommand extends Command
{
    protected $signature = 'cms:theme {action=list : list|activate} {theme?}';

    protected $description = 'Manage CMS themes from the command line';

    public function handle(ThemeManager $themes): int
    {
        if ($this->argument('action') === 'activate') {
            $themes->switch((string) $this->argument('theme'));
            $this->components->info('Activated theme '.$this->argument('theme'));

            return self::SUCCESS;
        }

        $active = $themes->activeSlug();
        $this->table(['Slug', 'Name', 'Version', 'Parent', 'Status'], collect($themes->all())->map(fn ($t) => [
            $t['slug'], $t['name'], $t['version'], $t['parent'] ?? '—', $t['slug'] === $active ? '<info>active</info>' : '',
        ])->values());

        return self::SUCCESS;
    }
}
