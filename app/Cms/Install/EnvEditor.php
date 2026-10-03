<?php

namespace App\Cms\Install;

use RuntimeException;

/**
 * Reads/updates KEY=value pairs in a .env file while keeping comments,
 * ordering and every other line intact (like editing wp-config.php).
 */
class EnvEditor
{
    public function __construct(protected string $path) {}

    public function get(string $key): ?string
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $m)) {
            return null;
        }
        $value = trim($m[1]);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $inner = substr($value, 1, -1);

            return $value[0] === '"' ? stripcslashes($inner) : $inner;
        }

        return $value;
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public function set(array $values): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Invalid environment key: {$key}");
            }

            $line = $key.'='.static::format($value);
            $active = '/^'.preg_quote($key, '/').'=.*$/m';
            $commented = '/^#\s*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($active, $contents)) {
                $contents = preg_replace_callback($active, fn () => $line, $contents, 1);
            } elseif (preg_match($commented, $contents)) {
                $contents = preg_replace_callback($commented, fn () => $line, $contents, 1);
            } else {
                $contents = rtrim($contents, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        $dir = dirname($this->path);
        if ((is_file($this->path) && ! is_writable($this->path)) || (! is_file($this->path) && ! is_writable($dir))) {
            throw new RuntimeException('The .env file is not writable. Make it writable by the web server and try again.');
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the .env file.');
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->path, true);
        }
    }

    /**
     * Quote values so phpdotenv reads them back verbatim.
     */
    public static function format(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = str_replace(["\r", "\n"], '', (string) $value);

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-,]+$/', $value)) {
            return $value;
        }

        // Single quotes are literal in phpdotenv (no ${VAR} interpolation, no escapes).
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.addcslashes($value, "\\\"\$").'"';
    }
}
