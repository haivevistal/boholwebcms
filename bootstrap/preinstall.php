<?php

/*
|--------------------------------------------------------------------------
| Pre-install bootstrap (runs before Laravel, plain PHP)
|--------------------------------------------------------------------------
| Like WordPress' wp-load.php checks: on a fresh upload to a server there
| is no vendor/ folder or .env yet. This makes sure:
|   1. Composer dependencies exist (otherwise shows how to install them)
|   2. a .env file exists with an APP_KEY, so Laravel can boot and the web
|      installer at /install can collect database & site details.
*/

return (function (string $base): void {
    $fail = function (string $title, string $html): void {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.htmlspecialchars($title).'</title><style>'
            .'body{margin:0;background:#f1f5f9;font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;color:#0f172a;display:grid;place-items:center;min-height:100vh;padding:24px}'
            .'.box{max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px 32px;box-shadow:0 1px 2px rgb(0 0 0/.05)}'
            .'h1{font-size:20px;margin:0 0 8px}code,pre{background:#0f172a;color:#e2e8f0;border-radius:8px;font-size:13px}code{padding:2px 6px}pre{padding:12px 14px;overflow:auto}'
            .'.logo{display:inline-grid;place-items:center;width:40px;height:40px;border-radius:10px;background:#4f46e5;color:#fff;font-weight:800;margin-bottom:14px}</style></head>'
            .'<body><div class="box"><div class="logo">B</div><h1>'.htmlspecialchars($title).'</h1>'.$html.'</div></body></html>';
        exit;
    };

    if (! is_file($base.'/vendor/autoload.php')) {
        $fail('Almost there — install dependencies', '<p>BoholwebCMS needs its PHP dependencies. In the site folder on your server, run:</p><pre>composer install --no-dev --optimize-autoloader</pre><p>Then reload this page to start the installer.</p>');
    }

    $env = $base.'/.env';

    if (! is_file($env)) {
        $example = $base.'/.env.example';
        if (! is_file($example) || ! @copy($example, $env)) {
            $fail('Can’t create the .env file', '<p>The installer needs to create a <code>.env</code> configuration file in the site folder, but the folder is not writable.</p><p>Either make the folder writable by the web server, or copy <code>.env.example</code> to <code>.env</code> yourself and reload.</p>');
        }
    }

    $contents = (string) @file_get_contents($env);

    if (! preg_match('/^APP_KEY=\S+/m', $contents)) {
        $key = 'APP_KEY=base64:'.base64_encode(random_bytes(32));
        $contents = preg_match('/^APP_KEY=.*$/m', $contents)
            ? preg_replace('/^APP_KEY=.*$/m', $key, $contents)
            : $key.PHP_EOL.$contents;

        if (! is_writable($env) || @file_put_contents($env, $contents) === false) {
            $fail('Can’t write the application key', '<p>The <code>.env</code> file exists but is not writable, so the installer can’t generate an application key.</p><p>Make <code>.env</code> writable by the web server, or run <code>php artisan key:generate</code>, then reload.</p>');
        }
    }
})(dirname(__DIR__));
