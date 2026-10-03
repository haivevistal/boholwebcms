<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Frontend\AjaxController;
use App\Http\Controllers\Frontend\AssetController;
use App\Http\Controllers\Frontend\CommentController;
use App\Http\Controllers\Frontend\FrontendController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', [AuthController::class, 'logoutGet'])->name('logout.get');
Route::get('/install', [AuthController::class, 'installNotice'])->name('install.notice');

/*
|--------------------------------------------------------------------------
| Admin  (/admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/quick-draft', [Admin\DashboardController::class, 'quickDraft'])->name('quick-draft')->middleware('cap:edit_posts');

    // Posts, Pages and custom post types
    Route::controller(Admin\PostController::class)->prefix('content/{type}')->name('content.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::post('/empty-trash', 'emptyTrash')->name('empty-trash');
        Route::get('/{post}/edit', 'edit')->name('edit')->whereNumber('post');
        Route::put('/{post}', 'update')->name('update')->whereNumber('post');
        Route::post('/{post}/trash', 'trash')->name('trash')->whereNumber('post');
        Route::post('/{post}/restore', 'restore')->name('restore')->whereNumber('post');
        Route::post('/{post}/duplicate', 'duplicate')->name('duplicate')->whereNumber('post');
        Route::delete('/{post}', 'destroy')->name('destroy')->whereNumber('post');
    });

    // Categories, Tags and custom taxonomies
    Route::controller(Admin\TermController::class)->prefix('terms/{taxonomy}')->name('terms.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/json', 'json')->name('json');
        Route::post('/', 'store')->name('store');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::put('/{term}', 'update')->name('update')->whereNumber('term');
        Route::delete('/{term}', 'destroy')->name('destroy')->whereNumber('term');
    });

    // Comments
    Route::controller(Admin\CommentController::class)->prefix('comments')->name('comments.')->middleware('cap:moderate_comments')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::patch('/{comment}/status', 'status')->name('status');
        Route::put('/{comment}', 'update')->name('update');
        Route::post('/{comment}/reply', 'reply')->name('reply');
        Route::delete('/{comment}', 'destroy')->name('destroy');
    });

    // Media library
    Route::controller(Admin\MediaController::class)->prefix('media')->name('media.')->middleware('cap:upload_files')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/json', 'json')->name('json');
        Route::post('/', 'store')->name('store');
        Route::post('/bulk-delete', 'bulkDestroy')->name('bulk-destroy');
        Route::put('/{media}', 'update')->name('update');
        Route::delete('/{media}', 'destroy')->name('destroy');
    });

    // Appearance
    Route::controller(Admin\ThemeController::class)->group(function () {
        Route::get('/themes', 'index')->name('themes.index')->middleware('cap:switch_themes');
        Route::post('/themes/upload', 'upload')->name('themes.upload')->middleware('cap:install_themes');
        Route::post('/themes/dismiss-error', 'dismissError')->name('themes.dismiss-error');
        Route::post('/themes/{slug}/activate', 'activate')->name('themes.activate')->middleware('cap:switch_themes');
        Route::delete('/themes/{slug}', 'destroy')->name('themes.destroy')->middleware('cap:delete_themes');
        Route::get('/customize', 'customize')->name('customize')->middleware('cap:edit_theme_options');
        Route::post('/customize', 'saveCustomize')->name('customize.save')->middleware('cap:edit_theme_options');
    });
    Route::get('/theme-editor', [Admin\FileEditorController::class, 'theme'])->name('theme-editor')->middleware('cap:edit_themes');
    Route::post('/theme-editor', [Admin\FileEditorController::class, 'saveTheme'])->name('theme-editor.save')->middleware('cap:edit_themes');

    // Plugins
    Route::controller(Admin\PluginController::class)->prefix('plugins')->name('plugins.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('cap:activate_plugins');
        Route::get('/add', 'add')->name('add')->middleware('cap:install_plugins');
        Route::post('/upload', 'upload')->name('upload')->middleware('cap:install_plugins');
        Route::post('/create', 'create')->name('create')->middleware('cap:install_plugins');
        Route::post('/activate', 'activate')->name('activate')->middleware('cap:activate_plugins');
        Route::post('/deactivate', 'deactivate')->name('deactivate')->middleware('cap:activate_plugins');
        Route::post('/bulk', 'bulk')->name('bulk')->middleware('cap:activate_plugins');
        Route::post('/delete', 'destroy')->name('destroy')->middleware('cap:delete_plugins');
        Route::post('/dismiss-errors', 'dismissErrors')->name('dismiss-errors')->middleware('cap:activate_plugins');
    });
    Route::get('/plugin-editor', [Admin\FileEditorController::class, 'plugin'])->name('plugin-editor')->middleware('cap:edit_plugins');
    Route::post('/plugin-editor', [Admin\FileEditorController::class, 'savePlugin'])->name('plugin-editor.save')->middleware('cap:edit_plugins');

    // Users & roles
    Route::get('/profile', [Admin\UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [Admin\UserController::class, 'updateProfile'])->name('profile.update');
    Route::controller(Admin\UserController::class)->prefix('users')->name('users.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('cap:list_users');
        Route::get('/create', 'create')->name('create')->middleware('cap:create_users');
        Route::post('/', 'store')->name('store')->middleware('cap:create_users');
        Route::post('/bulk', 'bulk')->name('bulk')->middleware('cap:edit_users');
        Route::get('/{user}/edit', 'edit')->name('edit')->middleware('cap:edit_users');
        Route::put('/{user}', 'update')->name('update')->middleware('cap:edit_users');
        Route::delete('/{user}', 'destroy')->name('destroy')->middleware('cap:delete_users');
    });
    Route::controller(Admin\RoleController::class)->prefix('roles')->name('roles.')->middleware('cap:manage_roles')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{role}', 'update')->name('update');
        Route::delete('/{role}', 'destroy')->name('destroy');
    });

    // Tools
    Route::controller(Admin\ToolController::class)->prefix('tools')->name('tools.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('cap:edit_posts');
        Route::get('/export', 'export')->name('export')->middleware('cap:export');
        Route::post('/export', 'download')->name('export.download')->middleware('cap:export');
        Route::get('/import', 'import')->name('import')->middleware('cap:import');
        Route::post('/import', 'runImport')->name('import.run')->middleware('cap:import');
        Route::get('/site-health', 'siteHealth')->name('site-health')->middleware('cap:manage_options');
        Route::get('/hooks', 'hooks')->name('hooks')->middleware('cap:manage_options');
        Route::get('/clear-cache', 'clearCachePage')->name('clear-cache')->middleware('cap:manage_options');
        Route::post('/clear-cache', 'clearCache')->name('clear-cache.run')->middleware('cap:manage_options');
    });

    // Settings (core + plugin settings pages)
    Route::get('/settings', fn () => redirect()->route('admin.settings.show', 'general'))->name('settings');
    Route::get('/settings/{page}', [Admin\SettingsController::class, 'show'])->name('settings.show');
    Route::post('/settings/{page}', [Admin\SettingsController::class, 'update'])->name('settings.update');

    // Pages registered by plugins/themes via add_menu_page() / add_submenu_page()
    Route::match(['get', 'post', 'put', 'patch', 'delete'], '/page/{slug}', [Admin\AdminPageController::class, 'show'])
        ->name('page')->where('slug', '[A-Za-z0-9_\-\.]+');

    // Generic form handler & AJAX for plugins: do_action("admin_post_{action}") / ("admin_ajax_{action}")
    Route::post('/admin-post', [Admin\AdminPageController::class, 'adminPost'])->name('admin-post');
    Route::post('/ajax', [Admin\AdminPageController::class, 'ajax'])->name('ajax');
});

/*
|--------------------------------------------------------------------------
| Public endpoints
|--------------------------------------------------------------------------
*/
Route::post('/cms-ajax', [AjaxController::class, 'handle'])->name('cms.ajax');
Route::match(['get', 'post'], '/cms-webhook/{action}', [AjaxController::class, 'webhook'])->name('cms.webhook')->where('action', '[A-Za-z0-9_\-]+');
Route::post('/comments', [CommentController::class, 'store'])->name('comments.store')->middleware('throttle:10,1');
Route::post('/post-password/{post}', [FrontendController::class, 'unlock'])->name('post.unlock')->middleware('throttle:10,1');

// Public plugin/theme assets: /content/{plugins|themes}/{slug}/{path}
Route::get('/'.trim(config('cms.content_url_prefix', 'content'), '/').'/{type}/{path}', [AssetController::class, 'show'])
    ->where(['type' => 'plugins|themes', 'path' => '.*'])
    ->name('cms.asset');

/*
|--------------------------------------------------------------------------
| Plugin routes
|--------------------------------------------------------------------------
| add_action('cms_routes', function (\Illuminate\Routing\Router $router) {
|     $router->get('/shop/cart', [CartController::class, 'show']);
| });
*/
if (cms_installed()) {
    do_action('cms_routes', app('router'));
}

/*
|--------------------------------------------------------------------------
| Front end (theme) — must stay last
|--------------------------------------------------------------------------
*/
Route::get('/{path?}', [FrontendController::class, 'show'])->where('path', '.*')->name('front');
