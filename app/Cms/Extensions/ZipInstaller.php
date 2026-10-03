<?php

namespace App\Cms\Extensions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Safely extracts an uploaded plugin/theme .zip into a content directory.
 */
class ZipInstaller
{
    /**
     * @param  callable(string $dir): bool  $validate  receives the extracted package folder
     * @return string  the installed folder name
     */
    public function install(string $zipPath, string $targetRoot, callable $validate, bool $overwrite = false): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to install packages.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The uploaded file is not a valid zip archive.');
        }

        // Reject path traversal and absolute paths before extracting anything.
        $roots = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[a-zA-Z]:#', $name)) {
                $zip->close();
                throw new RuntimeException("Unsafe path in archive: {$name}");
            }
            if (str_starts_with($name, '__MACOSX/')) {
                continue;
            }
            $roots[explode('/', $name)[0]] = true;
        }

        $tmp = storage_path('app/tmp/'.Str::random(16));
        File::ensureDirectoryExists($tmp);

        try {
            if (! $zip->extractTo($tmp)) {
                throw new RuntimeException('Could not extract the archive.');
            }
            $zip->close();
            File::deleteDirectory($tmp.'/__MACOSX');

            // Package either sits inside a single top-level folder or at the zip root.
            $entries = array_values(array_filter(scandir($tmp), fn ($e) => ! in_array($e, ['.', '..'], true)));
            if (count($entries) === 1 && is_dir($tmp.'/'.$entries[0])) {
                $folder = $entries[0];
                $source = $tmp.'/'.$folder;
            } else {
                $folder = Str::slug(pathinfo($zipPath, PATHINFO_FILENAME)) ?: 'package-'.Str::random(6);
                $source = $tmp;
            }

            $folder = Str::slug($folder) ?: $folder;

            if (! $validate($source)) {
                throw new RuntimeException('The package is not valid (missing required header/manifest).');
            }

            $destination = rtrim($targetRoot, '/').'/'.$folder;
            if (is_dir($destination)) {
                if (! $overwrite) {
                    throw new RuntimeException("Destination folder already exists: {$folder}. Delete it first or choose 'replace'.");
                }
                File::deleteDirectory($destination);
            }

            File::ensureDirectoryExists($targetRoot);
            if (! File::moveDirectory($source, $destination)) {
                File::copyDirectory($source, $destination);
            }

            return $folder;
        } finally {
            File::deleteDirectory($tmp);
        }
    }
}
