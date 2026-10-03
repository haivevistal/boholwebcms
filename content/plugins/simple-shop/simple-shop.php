<?php
/**
 * Plugin Name: Simple Shop
 * Plugin URI: https://boholwebwp.com
 * Description: Turns the site into a store — products (custom post type), categories, cart & checkout shortcodes, orders, a Shop admin menu, a settings page under Settings, a tool under Tools, a dashboard widget and a React reports screen. A reference implementation of the BoholwebCMS plugin API.
 * Version: 1.0.0
 * Author: BoholWeb
 * Author URI: https://boholwebwp.com
 * Requires CMS: 1.0
 * Requires PHP: 8.2
 * License: MIT
 */

defined('CMS_LOADED') || exit;

define('SIMPLE_SHOP_FILE', __FILE__);
define('SIMPLE_SHOP_VERSION', '1.0.0');

require_once __DIR__.'/src/Order.php';
require_once __DIR__.'/src/Shop.php';
require_once __DIR__.'/src/Cart.php';
require_once __DIR__.'/src/Frontend.php';
require_once __DIR__.'/src/Admin.php';

use SimpleShop\Admin;
use SimpleShop\Frontend;
use SimpleShop\Shop;

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/
register_activation_hook(__FILE__, [Shop::class, 'activate']);
register_deactivation_hook(__FILE__, [Shop::class, 'deactivate']);

// `php artisan cms:install --demo` / `cms:plugin activate simple-shop --demo-content`
add_action('cms_demo_content', [Shop::class, 'createDemoContent']);

/*
|--------------------------------------------------------------------------
| Content model: post type, taxonomy, meta boxes
|--------------------------------------------------------------------------
*/
add_action('init', [Shop::class, 'registerContent']);

/*
|--------------------------------------------------------------------------
| Front end: shortcodes, routes, content filters, assets, menu item
|--------------------------------------------------------------------------
*/
Frontend::boot();

/*
|--------------------------------------------------------------------------
| Admin: menus, settings page, tools, dashboard widget, columns, scripts
|--------------------------------------------------------------------------
*/
Admin::boot();
