<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves public files from content/plugins and content/themes
 * (the wp-content equivalent). Only allow-listed extensions are served,
 * so PHP sources and config files are never exposed.
 */
class AssetController extends Controller
{
    protected const MIME = [
        'js' => 'text/javascript', 'mjs' => 'text/javascript', 'css' => 'text/css', 'map' => 'application/json',
        'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'txt' => 'text/plain',
    ];

    public function show(string $type, string $path): BinaryFileResponse
    {
        $root = realpath($type === 'plugins' ? config('cms.plugins_path') : config('cms.themes_path'));
        $file = $root ? realpath($root.'/'.$path) : false;

        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        abort_unless(in_array($ext, config('cms.public_asset_extensions', []), true), 404);

        // Never expose package manifests that may contain private info.
        abort_if(in_array(basename($file), ['composer.json', 'package.json', 'package-lock.json'], true), 404);

        return response()->file($file, [
            'Content-Type' => self::MIME[$ext] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
