<?php

use Illuminate\Support\Facades\Schedule;

// Fires the "cms_cron" action every minute (the equivalent of WP-Cron).
// Add `* * * * * php artisan schedule:run` to your server's crontab.
Schedule::call(function () {
    if (cms_installed()) {
        do_action('cms_cron');
    }
})->everyMinute()->name('cms-cron');

Schedule::call(function () {
    if (cms_installed()) {
        do_action('cms_cron_hourly');
    }
})->hourly()->name('cms-cron-hourly');

Schedule::call(function () {
    if (cms_installed()) {
        do_action('cms_cron_daily');
    }
})->daily()->name('cms-cron-daily');
