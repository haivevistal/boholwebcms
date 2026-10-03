<?php

namespace App\Cms\Install;

/**
 * Server checks shown on the first installer step.
 */
class Requirements
{
    public const MIN_PHP = '8.2.0';

    public function __construct(protected string $base) {}

    /**
     * @return array{passes: bool, groups: array<string, array<int, array{label:string, ok:bool, required:bool, detail:?string}>>}
     */
    public function check(): array
    {
        $php = [
            $this->item('PHP '.self::MIN_PHP.' or newer', version_compare(PHP_VERSION, self::MIN_PHP, '>='), true, 'Running PHP '.PHP_VERSION),
        ];

        $extensions = [];
        foreach (['pdo' => true, 'mbstring' => true, 'openssl' => true, 'tokenizer' => true, 'xml' => true, 'ctype' => true, 'fileinfo' => true, 'json' => true, 'curl' => false, 'zip' => true, 'gd' => false, 'intl' => false] as $ext => $required) {
            $detail = match ($ext) {
                'zip' => 'Needed to install plugins & themes from .zip files',
                'gd' => 'Needed to generate image thumbnails',
                default => null,
            };
            $extensions[] = $this->item($ext, extension_loaded($ext), $required, $detail);
        }

        $drivers = [];
        foreach (DatabaseProbe::DRIVERS as $key => $d) {
            $drivers[] = $this->item($d['label'].' ('.$d['pdo'].')', extension_loaded($d['pdo']), false, null);
        }
        // At least one database driver must be available.
        $anyDriver = (bool) array_filter($drivers, fn ($d) => $d['ok']);
        array_unshift($drivers, $this->item('At least one database driver', $anyDriver, true, null));

        $writable = [];
        foreach (['.env' => true, 'storage' => true, 'bootstrap/cache' => true, 'content/plugins' => true, 'content/themes' => true, 'public' => false, 'database' => false] as $path => $required) {
            $full = $this->base.'/'.$path;
            $ok = file_exists($full) ? is_writable($full) : is_writable(dirname($full));
            $detail = match ($path) {
                'public' => 'Needed to link public/storage for media uploads',
                'database' => 'Only needed for SQLite',
                'content/plugins', 'content/themes' => 'Needed to install and edit plugins/themes from the admin',
                default => null,
            };
            $writable[] = $this->item($path, $ok, $required, $detail);
        }

        $groups = ['PHP' => $php, 'PHP extensions' => $extensions, 'Database drivers' => $drivers, 'Writable folders' => $writable];

        $passes = true;
        foreach ($groups as $items) {
            foreach ($items as $item) {
                if ($item['required'] && ! $item['ok']) {
                    $passes = false;
                }
            }
        }

        return ['passes' => $passes, 'groups' => $groups];
    }

    protected function item(string $label, bool $ok, bool $required, ?string $detail): array
    {
        return compact('label', 'ok', 'required', 'detail');
    }
}
