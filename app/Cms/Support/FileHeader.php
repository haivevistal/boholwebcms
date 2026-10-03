<?php

namespace App\Cms\Support;

/**
 * Reads WordPress-style file headers:
 *
 *   /**
 *    * Plugin Name: Simple Shop
 *    * Description: Turns the site into a store.
 *    * Version: 1.0.0
 *    * Author: BoholWeb
 *    *\/
 */
class FileHeader
{
    public const PLUGIN_HEADERS = [
        'name' => 'Plugin Name',
        'uri' => 'Plugin URI',
        'description' => 'Description',
        'version' => 'Version',
        'author' => 'Author',
        'author_uri' => 'Author URI',
        'requires' => 'Requires CMS',
        'requires_php' => 'Requires PHP',
        'requires_plugins' => 'Requires Plugins',
        'license' => 'License',
        'text_domain' => 'Text Domain',
        'network' => 'Network',
    ];

    public static function read(string $file, array $headers, int $bytes = 8192): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return array_fill_keys(array_keys($headers), '');
        }

        $handle = fopen($file, 'r');
        $data = (string) fread($handle, $bytes);
        fclose($handle);

        return self::parse($data, $headers);
    }

    public static function parse(string $data, array $headers): array
    {
        $data = str_replace("\r", "\n", $data);
        $out = [];

        foreach ($headers as $key => $label) {
            if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($label, '/').':(.*)$/mi', $data, $m) && $m[1]) {
                $out[$key] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1]));
            } else {
                $out[$key] = '';
            }
        }

        return $out;
    }
}
