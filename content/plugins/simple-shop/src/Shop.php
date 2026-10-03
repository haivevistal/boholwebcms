<?php

namespace SimpleShop;

use App\Models\Post;
use App\Models\Role;
use App\Models\Term;

/**
 * Shared helpers + lifecycle for Simple Shop.
 */
class Shop
{
    public const CURRENCIES = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PHP' => '₱', 'JPY' => '¥', 'AUD' => 'A$'];

    public static function registerContent(): void
    {
        register_post_type('product', [
            'label' => 'Products',
            'labels' => ['singular_name' => 'Product', 'all_items' => 'All Products', 'add_new' => 'Add Product'],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'shop'],
            'menu_icon' => 'shopping-bag',
            'menu_position' => 26,
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'taxonomies' => ['product_cat'],
            'capability_type' => 'product',
        ]);

        register_taxonomy('product_cat', 'product', [
            'label' => 'Product Categories',
            'labels' => ['singular_name' => 'Product Category', 'menu_name' => 'Categories'],
            'hierarchical' => true,
            'rewrite' => ['slug' => 'product-category'],
            'capability' => 'manage_shop',
        ]);

        add_meta_box('product_data', 'Product data', 'product', [
            'context' => 'normal',
            'priority' => 1,
            'fields' => [
                ['name' => '_price', 'label' => 'Regular price', 'type' => 'number', 'attributes' => ['step' => '0.01', 'min' => 0], 'width' => 'small'],
                ['name' => '_sale_price', 'label' => 'Sale price', 'type' => 'number', 'attributes' => ['step' => '0.01', 'min' => 0], 'width' => 'small', 'description' => 'Leave empty when the product is not on sale.'],
                ['name' => '_sku', 'label' => 'SKU', 'type' => 'text', 'width' => 'small'],
                ['name' => '_manage_stock', 'label' => 'Track stock quantity for this product', 'type' => 'checkbox', 'default' => false],
                ['name' => '_stock', 'label' => 'Stock quantity', 'type' => 'number', 'width' => 'small', 'default' => 0],
            ],
        ]);

        // Example of an HTML meta box: its inputs are posted as meta_box[...]
        add_meta_box('product_badge', 'Badge', 'product', [
            'context' => 'side',
            'callback' => function (?Post $post) {
                $badge = $post?->getMeta('_badge') ?? '';
                $options = ['' => 'None', 'new' => 'New', 'hot' => 'Hot', 'limited' => 'Limited'];
                $html = '<select name="meta_box[product_badge]" style="width:100%">';
                foreach ($options as $value => $label) {
                    $html .= '<option value="'.e($value).'"'.selected($badge, $value).'>'.e($label).'</option>';
                }

                return $html.'</select><p class="description">Shown on the product card.</p>';
            },
        ]);

        add_action('save_post_product', function (Post $post, bool $update, $request = null) {
            if ($request && $request->has('meta_box.product_badge')) {
                $post->setMeta('_badge', sanitize_key($request->input('meta_box.product_badge')));
            }
        }, 10, 3);

        // Let themes read product data from post.meta.
        add_filter('post_public_meta', function (array $meta, Post $post) {
            if ($post->type !== 'product') {
                return $meta;
            }

            return $meta + [
                'price' => static::price($post),
                'regular_price' => (float) $post->getMeta('_price', 0),
                'on_sale' => static::onSale($post),
                'price_html' => static::priceHtml($post),
                'sku' => $post->getMeta('_sku'),
                'in_stock' => static::inStock($post),
                'badge' => $post->getMeta('_badge'),
            ];
        }, 10, 2);
    }

    /* ---------------------------------------------------------------- pricing */

    public static function price(Post $product): float
    {
        return static::onSale($product) ? (float) $product->getMeta('_sale_price') : (float) $product->getMeta('_price', 0);
    }

    public static function onSale(Post $product): bool
    {
        $sale = $product->getMeta('_sale_price');

        return $sale !== null && $sale !== '' && (float) $sale < (float) $product->getMeta('_price', 0);
    }

    public static function inStock(Post $product, int $qty = 1): bool
    {
        if (! $product->getMeta('_manage_stock')) {
            return true;
        }

        return (int) $product->getMeta('_stock', 0) >= $qty;
    }

    public static function currency(): string
    {
        return (string) get_option('shop_currency', 'USD');
    }

    public static function money(float $amount): string
    {
        $symbol = static::CURRENCIES[static::currency()] ?? static::currency().' ';
        $formatted = number_format($amount, 2);
        $out = get_option('shop_currency_position', 'left') === 'right' ? $formatted.' '.$symbol : $symbol.$formatted;

        return apply_filters('shop_money', $out, $amount);
    }

    public static function priceHtml(Post $product): string
    {
        if (static::onSale($product)) {
            return '<del>'.e(static::money((float) $product->getMeta('_price'))).'</del> <ins>'.e(static::money(static::price($product))).'</ins>';
        }

        return '<span>'.e(static::money(static::price($product))).'</span>';
    }

    public static function pageUrl(string $which): string
    {
        $id = (int) get_option("shop_{$which}_page");
        $page = $id ? Post::find($id) : null;

        return $page ? $page->permalink : url('/');
    }

    /* -------------------------------------------------------------- lifecycle */

    public static function activate(): void
    {
        // Defaults (add_option never overwrites existing values).
        add_option('shop_currency', 'USD');
        add_option('shop_currency_position', 'left');
        add_option('shop_guest_checkout', true);
        add_option('shop_order_email', get_option('admin_email'));
        add_option('shop_low_stock', 2);
        add_option('shop_payment_instructions', 'Pay with cash on delivery.');

        // Cart & checkout pages with their shortcodes, like WooCommerce.
        foreach (['cart' => ['Cart', '[cart]'], 'checkout' => ['Checkout', '[checkout]']] as $key => [$title, $shortcode]) {
            $id = (int) get_option("shop_{$key}_page");
            if (! $id || ! Post::find($id)) {
                $page = cms_insert_post(['type' => 'page', 'status' => 'publish', 'title' => $title, 'slug' => $key, 'content' => "<p>{$shortcode}</p>", 'comment_status' => 'closed', 'menu_order' => 50]);
                update_option("shop_{$key}_page", $page->id);
            }
        }

        // A role for store staff.
        Role::firstOrCreate(['slug' => 'shop_manager'], [
            'name' => 'Shop Manager',
            'capabilities' => ['read', 'upload_files', 'manage_shop', 'edit_products', 'edit_others_products', 'publish_products', 'delete_products', 'delete_others_products', 'read_private_products'],
        ]);
    }

    public static function deactivate(): void
    {
        // Keep data; uninstall.php removes it when the plugin is deleted.
    }

    public static function createDemoContent(): void
    {
        if (Post::where('type', 'product')->exists()) {
            return;
        }
        $apparel = Term::firstOrCreate(['taxonomy' => 'product_cat', 'slug' => 'apparel'], ['name' => 'Apparel']);
        $home = Term::firstOrCreate(['taxonomy' => 'product_cat', 'slug' => 'home'], ['name' => 'Home & Living']);

        $products = [
            ['Classic Tee', 'A soft cotton tee for everyday wear.', 24, 19, 'TEE-01', $apparel, 'new'],
            ['Everyday Hoodie', 'Warm fleece hoodie with a relaxed fit.', 59, null, 'HOOD-01', $apparel, 'hot'],
            ['Canvas Cap', 'Adjustable cap with an embroidered logo.', 18, null, 'CAP-01', $apparel, ''],
            ['Ceramic Mug', 'Stoneware mug, 350 ml, dishwasher safe.', 14, 11, 'MUG-01', $home, ''],
            ['Linen Throw', 'Stonewashed linen throw blanket.', 89, null, 'THR-01', $home, 'limited'],
            ['Scented Candle', 'Hand-poured soy candle, 40 hour burn.', 22, null, 'CAN-01', $home, ''],
        ];

        foreach ($products as $i => [$title, $excerpt, $price, $sale, $sku, $cat, $badge]) {
            cms_insert_post([
                'type' => 'product', 'status' => 'publish', 'title' => $title, 'excerpt' => $excerpt,
                'content' => "<p>{$excerpt} Designed to last and made with care.</p><ul><li>Free returns within 30 days</li><li>Ships in 1–2 business days</li></ul>",
                'menu_order' => $i,
                'terms' => ['product_cat' => [$cat->id]],
                'meta' => ['_price' => $price, '_sale_price' => $sale, '_sku' => $sku, '_manage_stock' => true, '_stock' => 10 + $i * 3, '_badge' => $badge],
            ]);
        }

        // A shop page using the [products] shortcode.
        if (! Post::where('type', 'page')->where('slug', 'store')->exists()) {
            cms_insert_post(['type' => 'page', 'status' => 'publish', 'title' => 'Store', 'slug' => 'store', 'menu_order' => 5, 'comment_status' => 'closed',
                'content' => '<h2>Featured products</h2><p>[products limit="6" columns="3"]</p>']);
        }
    }
}
