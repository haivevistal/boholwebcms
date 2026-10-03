<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// First run on a server: check dependencies and create .env + APP_KEY so the
// web installer (/install) can boot. Does nothing once the site is set up.
if (! is_file(__DIR__.'/../storage/app/.cms-installed')) {
    require __DIR__.'/../bootstrap/preinstall.php';
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
