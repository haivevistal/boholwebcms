<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product name
    |--------------------------------------------------------------------------
    */
    'name' => env('CMS_NAME', 'BoholwebCMS'),

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Content directories (the equivalent of wp-content/plugins & /themes)
    |--------------------------------------------------------------------------
    */
    'plugins_path' => base_path('content/plugins'),
    'themes_path' => base_path('content/themes'),

    /*
    | URL prefix that serves public assets (js/css/images/fonts) from
    | plugins and themes, e.g. /content/plugins/simple-shop/assets/shop.css
    */
    'content_url_prefix' => 'content',

    /*
    | Files with these extensions can be served publicly from plugin/theme
    | folders. PHP & config files are never served.
    */
    'public_asset_extensions' => [
        'js', 'mjs', 'css', 'map', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'mp3', 'txt',
    ],

    /*
    | File editors: which extensions may be edited from the admin.
    */
    'editable_extensions' => ['php', 'js', 'jsx', 'mjs', 'ts', 'tsx', 'css', 'scss', 'json', 'md', 'txt', 'html', 'svg', 'blade.php'],

    'disallow_file_edit' => (bool) env('CMS_DISALLOW_FILE_EDIT', false),
    'disallow_file_mods' => (bool) env('CMS_DISALLOW_FILE_MODS', false),

    /*
    | Upload rules for the media library.
    */
    'media' => [
        'disk' => 'public',
        'directory' => 'media',
        'max_upload_kb' => 20480,
        'allowed_mimes' => 'jpg,jpeg,png,gif,webp,svg,avif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4,webm,mov',
    ],

    'default_theme' => 'aurora',

    /*
    | Marker file written by `php artisan cms:install`. Until it exists the
    | CMS does not boot plugins/themes and redirects to the install notice.
    */
    'installed_marker' => env('CMS_INSTALLED_MARKER', storage_path('app/.cms-installed')),
];
