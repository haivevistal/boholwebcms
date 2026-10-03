<?php

namespace SimpleShop;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Everything visitors see: shortcodes, routes, content filters, assets.
 */
class Frontend
{
    public static function boot(): void
    {
        add_action('init', [static::class, 'shortcodes']);
        add_action('cms_routes', [static::class, 'routes']);

        // Single product pages get price + add-to-cart above the description.
        add_filter('the_content', [static::class, 'productContent'], 5, 2);

        // "Cart (2)" in every theme's primary menu.
        add_filter('cms_nav_menu', function (array $items, string $location) {
            if ($location === 'primary') {
                $items[] = ['id' => 'shop-cart', 'title' => 'Cart ('.Cart::count().')', 'url' => Shop::pageUrl('cart'), 'children' => []];
            }

            return $items;
        }, 20, 2);

        // Front-end assets.
        add_action('enqueue_scripts', function () {
            enqueue_style('simple-shop', plugin_url(SIMPLE_SHOP_FILE, 'assets/shop.css'), [], SIMPLE_SHOP_VERSION);
            enqueue_script('simple-shop', plugin_url(SIMPLE_SHOP_FILE, 'assets/shop.js'), [], SIMPLE_SHOP_VERSION);
            localize_script('simple-shop', 'SimpleShop', ['cartUrl' => Shop::pageUrl('cart'), 'count' => Cart::count()]);
        });

        // Front-end AJAX: POST /cms-ajax {action: 'shop_add_to_cart', product_id, qty}
        $add = function (Request $request) {
            $product = Post::where('type', 'product')->where('status', 'publish')->find((int) $request->input('product_id'));
            if (! $product) {
                cms_send_json_error('Product not found', 404);
            }
            $qty = max(1, (int) $request->input('qty', 1));
            if (! Shop::inStock($product, (Cart::items()[$product->id] ?? 0) + $qty)) {
                cms_send_json_error('Sorry, not enough stock.', 422);
            }
            Cart::add($product->id, $qty);
            cms_send_json(['count' => Cart::count(), 'total' => Shop::money(Cart::total()), 'message' => "“{$product->title}” added to your cart."]);
        };
        add_action('ajax_shop_add_to_cart', $add);
        add_action('ajax_nopriv_shop_add_to_cart', $add);
    }

    /* ------------------------------------------------------------ routes */

    public static function routes(Router $router): void
    {
        $router->post('/shop/cart/add', function (Request $request) {
            $product = Post::where('type', 'product')->where('status', 'publish')->findOrFail((int) $request->input('product_id'));
            $qty = max(1, (int) $request->input('qty', 1));
            if (! Shop::inStock($product, (Cart::items()[$product->id] ?? 0) + $qty)) {
                return back()->with('error', 'Sorry, not enough stock for “'.$product->title.'”.');
            }
            Cart::add($product->id, $qty);

            return back()->with('success', '“'.$product->title.'” has been added to your cart.');
        })->name('shop.cart.add');

        $router->post('/shop/cart/update', function (Request $request) {
            foreach ((array) $request->input('qty', []) as $id => $qty) {
                Cart::set((int) $id, (int) $qty);
            }

            return back()->with('success', 'Cart updated.');
        })->name('shop.cart.update');

        $router->post('/shop/cart/remove/{id}', function (int $id) {
            Cart::set($id, 0);

            return back()->with('success', 'Item removed.');
        })->name('shop.cart.remove');

        $router->post('/shop/checkout', [static::class, 'checkout'])->name('shop.checkout');
    }

    public static function checkout(Request $request)
    {
        $lines = Cart::lines();
        if (! $lines) {
            return redirect(Shop::pageUrl('cart'))->with('error', 'Your cart is empty.');
        }
        if (! get_option('shop_guest_checkout', true) && ! is_user_logged_in()) {
            return back()->with('error', 'Please log in to place an order.');
        }

        $data = $request->validate([
            'customer_name' => 'required|string|max:190',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:50',
            'address' => 'required|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);

        foreach ($lines as $line) {
            if (! Shop::inStock($line['product'], $line['qty'])) {
                return back()->with('error', '“'.$line['product']->title.'” is out of stock.');
            }
        }

        $data = apply_filters('shop_checkout_data', $data, $lines);

        $order = Order::create($data + [
            'number' => strtoupper(Str::random(3)).'-'.now()->format('ymd').'-'.random_int(1000, 9999),
            'status' => 'processing',
            'user_id' => current_user_id() ?: null,
            'items' => array_map(fn ($l) => ['product_id' => $l['product']->id, 'title' => $l['product']->title, 'sku' => $l['product']->getMeta('_sku'), 'qty' => $l['qty'], 'price' => $l['price']], $lines),
            'subtotal' => Cart::subtotal(),
            'total' => Cart::total(),
            'currency' => Shop::currency(),
            'payment_method' => 'cod',
        ]);

        foreach ($lines as $line) {
            if ($line['product']->getMeta('_manage_stock')) {
                $line['product']->setMeta('_stock', max(0, (int) $line['product']->getMeta('_stock', 0) - $line['qty']));
            }
        }

        Cart::clear();
        session()->push('shop_my_orders', $order->number);

        do_action('shop_order_created', $order);

        return redirect(Shop::pageUrl('checkout').'?order='.$order->number)->with('success', 'Thank you! Your order has been received.');
    }

    /* -------------------------------------------------------- shortcodes */

    public static function shortcodes(): void
    {
        // [products limit="8" category="apparel" columns="4" orderby="menu_order"]
        add_shortcode('products', function ($atts) {
            $a = shortcode_atts(['limit' => 8, 'category' => '', 'columns' => 4, 'orderby' => 'menu_order', 'order' => 'asc', 'ids' => ''], $atts, 'products');
            $args = ['type' => 'product', 'limit' => (int) $a['limit'], 'orderby' => $a['orderby'], 'order' => $a['order']];
            if ($a['category'] !== '') {
                $args['term'] = array_map('trim', explode(',', $a['category']));
                $args['taxonomy'] = 'product_cat';
            }
            if ($a['ids'] !== '') {
                $args['include'] = array_map('intval', explode(',', $a['ids']));
            }
            $products = get_posts($args);
            if ($products->isEmpty()) {
                return '<p class="shop-empty">No products found.</p>';
            }

            $html = '<div class="shop-grid" style="--shop-cols:'.max(1, min(6, (int) $a['columns'])).'">';
            foreach ($products as $product) {
                $html .= static::card($product);
            }

            return $html.'</div>';
        });

        // [add_to_cart id="12" show_price="true"]
        add_shortcode('add_to_cart', function ($atts) {
            $a = shortcode_atts(['id' => 0, 'show_price' => 'true'], $atts, 'add_to_cart');
            $product = Post::where('type', 'product')->find((int) $a['id']);
            if (! $product) {
                return '';
            }

            return '<div class="shop-add">'.(filter_var($a['show_price'], FILTER_VALIDATE_BOOLEAN) ? '<span class="shop-price">'.Shop::priceHtml($product).'</span> ' : '').static::addToCartForm($product).'</div>';
        });

        // [cart_count]
        add_shortcode('cart_count', fn () => '<span data-shop-cart-count>'.Cart::count().'</span>');

        add_shortcode('cart', [static::class, 'cartShortcode']);
        add_shortcode('checkout', [static::class, 'checkoutShortcode']);
    }

    public static function card(Post $product): string
    {
        $img = $product->featuredMedia?->sizeUrl('medium');
        $badge = $product->getMeta('_badge');
        $cats = $product->terms->where('taxonomy', 'product_cat')->pluck('name')->implode(', ');

        $html = '<div class="shop-card">';
        $html .= '<a class="shop-card__media" href="'.e($product->permalink).'">'
            .($img ? '<img src="'.e($img).'" alt="'.e($product->title).'" loading="lazy">' : '<span class="shop-card__ph">'.e(mb_substr($product->title, 0, 1)).'</span>')
            .($badge ? '<span class="shop-badge shop-badge--'.e($badge).'">'.e(ucfirst($badge)).'</span>' : '')
            .(Shop::onSale($product) ? '<span class="shop-badge shop-badge--sale">Sale</span>' : '')
            .'</a>';
        $html .= '<div class="shop-card__body">';
        if ($cats) {
            $html .= '<span class="shop-card__cat">'.e($cats).'</span>';
        }
        $html .= '<h3 class="shop-card__title"><a href="'.e($product->permalink).'">'.e($product->title).'</a></h3>';
        $html .= '<div class="shop-card__foot"><span class="shop-price">'.Shop::priceHtml($product).'</span>'.static::addToCartForm($product, true).'</div>';

        return $html.'</div></div>';
    }

    public static function addToCartForm(Post $product, bool $compact = false): string
    {
        if (! Shop::inStock($product)) {
            return '<span class="shop-stock shop-stock--out">Out of stock</span>';
        }

        return '<form class="shop-add-form" method="post" action="'.e(route('shop.cart.add')).'" data-shop-ajax>'
            .csrf_field()
            .'<input type="hidden" name="product_id" value="'.$product->id.'">'
            .($compact ? '' : '<input class="shop-qty" type="number" name="qty" value="1" min="1">')
            .'<button type="submit" class="cms-button shop-button">Add to cart</button></form>';
    }

    public static function productContent(string $content, $post = null): string
    {
        if (! $post instanceof Post || $post->type !== 'product' || doing_filter('get_the_excerpt')) {
            return $content;
        }

        $stock = $post->getMeta('_manage_stock')
            ? (Shop::inStock($post) ? '<span class="shop-stock">'.(int) $post->getMeta('_stock').' in stock</span>' : '<span class="shop-stock shop-stock--out">Out of stock</span>')
            : '';
        $sku = $post->getMeta('_sku') ? '<span class="shop-sku">SKU: '.e($post->getMeta('_sku')).'</span>' : '';

        $box = '<div class="shop-product-summary">'
            .'<p class="shop-price shop-price--lg">'.Shop::priceHtml($post).'</p>'
            .($post->excerpt ? '<p class="shop-excerpt">'.e($post->excerpt).'</p>' : '')
            .static::addToCartForm($post)
            .'<p class="shop-meta">'.$stock.' '.$sku.'</p></div>';

        return apply_filters('shop_single_product_summary', $box, $post).$content;
    }

    public static function cartShortcode(): string
    {
        $lines = Cart::lines();
        if (! $lines) {
            return '<div class="shop-cart-empty"><p>Your cart is currently empty.</p><p><a class="cms-button" href="'.e(get_post_type_archive_link('product') ?? url('/')).'">Return to shop</a></p></div>';
        }

        $html = '<form class="shop-cart" method="post" action="'.e(route('shop.cart.update')).'">'.csrf_field();
        $html .= '<table class="shop-table"><thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead><tbody>';
        foreach ($lines as $l) {
            $p = $l['product'];
            $html .= '<tr><td><a href="'.e($p->permalink).'">'.e($p->title).'</a></td>'
                .'<td>'.e(Shop::money($l['price'])).'</td>'
                .'<td><input class="shop-qty" type="number" min="0" name="qty['.$p->id.']" value="'.$l['qty'].'"></td>'
                .'<td>'.e(Shop::money($l['line'])).'</td>'
                .'<td><button type="submit" formaction="'.e(route('shop.cart.remove', $p->id)).'" class="shop-remove" aria-label="Remove">×</button></td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<div class="shop-cart__actions"><button type="submit" class="cms-button cms-button--secondary">Update cart</button>'
            .'<div class="shop-totals"><span>Total</span><strong>'.e(Shop::money(Cart::total())).'</strong></div></div>';
        $html .= '<p class="shop-proceed"><a class="cms-button" href="'.e(Shop::pageUrl('checkout')).'">Proceed to checkout →</a></p></form>';

        return apply_filters('shop_cart_html', $html, $lines);
    }

    public static function checkoutShortcode(): string
    {
        // Thank-you view.
        if (($number = request()->query('order')) && in_array($number, (array) session('shop_my_orders', []), true)) {
            $order = Order::where('number', $number)->first();
            if ($order) {
                $html = '<div class="shop-thanks"><h2>Thank you. Your order has been received.</h2>'
                    .'<ul class="shop-order-overview"><li>Order number: <strong>'.e($order->number).'</strong></li><li>Date: <strong>'.e(format_cms_date($order->created_at)).'</strong></li>'
                    .'<li>Total: <strong>'.e(Shop::money((float) $order->total)).'</strong></li><li>Payment: <strong>Cash on delivery</strong></li></ul>'
                    .'<p>'.e((string) get_option('shop_payment_instructions')).'</p><table class="shop-table"><tbody>';
                foreach ($order->items as $item) {
                    $html .= '<tr><td>'.e($item['title']).' × '.(int) $item['qty'].'</td><td>'.e(Shop::money($item['price'] * $item['qty'])).'</td></tr>';
                }

                return $html.'</tbody></table></div>';
            }
        }

        $lines = Cart::lines();
        if (! $lines) {
            return '<p>Your cart is empty. <a href="'.e(get_post_type_archive_link('product') ?? url('/')).'">Continue shopping</a>.</p>';
        }

        $errors = session('errors');
        $err = fn ($k) => $errors && $errors->has($k) ? '<span class="shop-error">'.e($errors->first($k)).'</span>' : '';
        $old = fn ($k, $d = '') => e(old($k, $d));
        $user = current_user();

        $html = '<form class="shop-checkout" method="post" action="'.e(route('shop.checkout')).'">'.csrf_field()
            .'<div class="shop-checkout__grid"><div class="shop-checkout__fields"><h3>Billing details</h3>'
            .'<p><label>Full name *<input name="customer_name" value="'.$old('customer_name', $user?->name ?? '').'" required></label>'.$err('customer_name').'</p>'
            .'<p><label>Email *<input type="email" name="email" value="'.$old('email', $user?->email ?? '').'" required></label>'.$err('email').'</p>'
            .'<p><label>Phone<input name="phone" value="'.$old('phone').'"></label></p>'
            .'<p><label>Shipping address *<textarea name="address" rows="3" required>'.$old('address').'</textarea></label>'.$err('address').'</p>'
            .'<p><label>Order notes<textarea name="notes" rows="2">'.$old('notes').'</textarea></label></p></div>'
            .'<div class="shop-checkout__review"><h3>Your order</h3><table class="shop-table"><tbody>';
        foreach ($lines as $l) {
            $html .= '<tr><td>'.e($l['product']->title).' × '.$l['qty'].'</td><td>'.e(Shop::money($l['line'])).'</td></tr>';
        }
        $html .= '<tr class="shop-total-row"><th>Total</th><th>'.e(Shop::money(Cart::total())).'</th></tr></tbody></table>'
            .'<p class="shop-payment">💵 Cash on delivery — '.e((string) get_option('shop_payment_instructions')).'</p>'
            .apply_filters('shop_checkout_before_button', '')
            .'<button type="submit" class="cms-button shop-place-order">Place order</button></div></div></form>';

        return $html;
    }
}
