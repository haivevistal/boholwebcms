<?php
/**
 * Runs when Simple Shop is deleted from Plugins → Installed Plugins.
 * Removes everything the plugin created (WordPress uninstall.php convention).
 */

defined('CMS_UNINSTALL_PLUGIN') || exit;

use App\Models\Post;
use App\Models\Role;
use App\Models\Term;
use Illuminate\Support\Facades\Schema;

Schema::dropIfExists('shop_orders');

Post::where('type', 'product')->get()->each->delete();
Term::where('taxonomy', 'product_cat')->delete();

foreach (['shop_currency', 'shop_currency_position', 'shop_cart_page', 'shop_checkout_page', 'shop_guest_checkout', 'shop_order_email', 'shop_payment_instructions', 'shop_low_stock'] as $option) {
    delete_option($option);
}

Role::where('slug', 'shop_manager')->delete();
