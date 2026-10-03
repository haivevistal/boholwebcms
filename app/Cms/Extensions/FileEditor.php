<?php

namespace App\Cms\Extensions;

use ParseError;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Backs the Theme File Editor and Plugin File Editor.
 * Confines every read/write to the package root and refuses to save PHP
 * that doesn't parse (so a typo can't take the whole site down).
 */
class FileEditor
{
    public function files(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $finder = (new Finder)->files()->in($root)
            ->exclude(['node_modules', 'vendor', '.git'])
            ->ignoreDotFiles(true)
            ->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            if ($this->editable($rel) && $file->getSize() < 1024 * 1024) {
                $files[] = ['path' => $rel, 'size' => $file->getSize()];
            }
        }

        return $files;
    }

    public function read(string $root, string $relative): string
    {
        $path = $this->resolve($root, $relative);
        if (! is_file($path)) {
            throw new RuntimeException('File not found.');
        }

        return (string) file_get_contents($path);
    }

    public function write(string $root, string $relative, string $contents): void
    {
        if (config('cms.disallow_file_edit')) {
            throw new RuntimeException('File editing is disabled (CMS_DISALLOW_FILE_EDIT).');
        }

        $path = $this->resolve($root, $relative);
        if (! is_file($path)) {
            throw new RuntimeException('File not found.');
        }
        if (! is_writable($path)) {
            throw new RuntimeException('File is not writable. Check file permissions on the server.');
        }

        if (str_ends_with($relative, '.php')) {
            $this->lint($contents);
        }
        if (str_ends_with($relative, '.json')) {
            json_decode($contents);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: '.json_last_error_msg());
            }
        }

        // Keep a backup next to the original in storage.
        $backupDir = storage_path('app/file-editor-backups');
        @mkdir($backupDir, 0775, true);
        @copy($path, $backupDir.'/'.md5($path).'-'.date('YmdHis').'.bak');

        file_put_contents($path, $contents);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    public function editable(string $relative): bool
    {
        $exts = config('cms.editable_extensions', []);
        foreach ($exts as $ext) {
            if (str_ends_with(strtolower($relative), '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    public function lint(string $code): void
    {
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException("PHP syntax error on line {$e->getLine()}: {$e->getMessage()} — file was not saved.");
        }
    }

    protected function resolve(string $root, string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            throw new RuntimeException('Invalid file path.');
        }

        $rootReal = realpath($root);
        $path = realpath($root.'/'.$relative);

        if (! $rootReal || ! $path || ! str_starts_with($path, $rootReal.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid file path.');
        }
        if (! $this->editable($relative)) {
            throw new RuntimeException('This file type cannot be edited.');
        }

        return $path;
    }
}
