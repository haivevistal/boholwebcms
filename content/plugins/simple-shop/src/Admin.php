<?php

namespace SimpleShop;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin integration — every extension point of the admin is used here:
 * top-level menu + submenus, a submenu under Tools, a settings page under
 * Settings, a tool card, a dashboard widget, list-table columns, row
 * actions, a React page, admin-post handlers and admin AJAX.
 */
class Admin
{
    public static function boot(): void
    {
        // Capability shown on Users → Roles.
        add_filter('cms_capabilities', function (array $groups) {
            $groups['Simple Shop'] = array_merge($groups['Simple Shop'] ?? [], ['manage_shop']);

            return $groups;
        });

        add_action('admin_menu', [static::class, 'menus']);
        add_action('init', [static::class, 'settings']);
        add_action('init', [static::class, 'tool']);

        add_dashboard_widget('shop_sales', 'Store overview', [static::class, 'dashboardWidget'], ['context' => 'side', 'priority' => 1, 'capability' => 'manage_shop']);

        // Products list table: extra columns.
        add_filter('manage_product_posts_columns', function (array $columns) {
            $date = $columns['date'] ?? 'Date';
            unset($columns['date']);

            return $columns + ['price' => 'Price', 'sku' => 'SKU', 'stock' => 'Stock', 'date' => $date];
        });
        add_filter('manage_product_posts_custom_column', function (string $html, string $column, Post $post) {
            return match ($column) {
                'price' => Shop::priceHtml($post),
                'sku' => e($post->getMeta('_sku') ?: '—'),
                'stock' => $post->getMeta('_manage_stock')
                    ? ((int) $post->getMeta('_stock') <= (int) get_option('shop_low_stock', 2)
                        ? '<span style="color:#b45309;font-weight:600">'.(int) $post->getMeta('_stock').' (low)</span>'
                        : '<span style="color:#15803d">'.(int) $post->getMeta('_stock').' in stock</span>')
                    : '<span class="pm-muted">—</span>',
                default => $html,
            };
        }, 10, 3);

        // Plugin action link on the Plugins screen.
        add_filter('plugin_action_links_simple-shop/simple-shop.php', fn ($links) => $links + [
            'settings' => '<a href="'.e(admin_url('settings/shop')).'">Settings</a>',
        ]);

        // React screen (no build step: assets/admin.js uses CMS.React.createElement).
        add_action('admin_enqueue_scripts', function () {
            enqueue_script('simple-shop-admin', plugin_url(SIMPLE_SHOP_FILE, 'assets/admin.js'), [], SIMPLE_SHOP_VERSION);
        });

        // Admin AJAX used by the React reports page.
        add_action('admin_ajax_shop_report', function (Request $request) {
            abort_unless(current_user_can('manage_shop'), 403);
            $days = max(7, min(90, (int) $request->input('days', 14)));
            $rows = Order::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->get(['total', 'created_at'])
                ->groupBy(fn ($o) => $o->created_at->format('Y-m-d'));
            $series = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = now()->subDays($i)->format('Y-m-d');
                $series[] = ['date' => $d, 'total' => round((float) ($rows[$d] ?? collect())->sum('total'), 2), 'orders' => ($rows[$d] ?? collect())->count()];
            }
            cms_send_json(['series' => $series, 'currency' => Shop::currency(), 'symbol' => Shop::CURRENCIES[Shop::currency()] ?? '']);
        });

        // Order status change from the Orders screen (admin-post.php style).
        add_action('admin_post_shop_update_order', function (Request $request) {
            abort_unless(current_user_can('manage_shop'), 403);
            $order = Order::findOrFail((int) $request->input('order_id'));
            $status = $request->input('status');
            abort_unless(array_key_exists($status, Order::STATUSES), 422);
            $old = $order->status;
            $order->update(['status' => $status]);
            do_action('shop_order_status_changed', $order, $old, $status);
            cms_send_response(back()->with('success', "Order {$order->number} marked as ".Order::STATUSES[$status].'.'));
        });

        // Tools → Shop Tools: export orders as CSV.
        add_action('admin_post_shop_export_orders', function () {
            abort_unless(current_user_can('manage_shop'), 403);
            cms_send_response(response()->streamDownload(function () {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Number', 'Date', 'Status', 'Customer', 'Email', 'Total', 'Currency']);
                Order::orderBy('id')->chunk(200, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        fputcsv($out, [$o->number, $o->created_at, $o->status, $o->customer_name, $o->email, $o->total, $o->currency]);
                    }
                });
                fclose($out);
            }, 'orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']));
        });
    }

    public static function menus(): void
    {
        add_menu_page('Shop', 'Shop', 'manage_shop', 'shop', [static::class, 'overviewPage'], 'store', 25.5);
        add_submenu_page('shop', 'Orders', 'Orders', 'manage_shop', 'shop-orders', [static::class, 'ordersPage']);
        add_submenu_page('shop', 'Reports', 'Reports', 'manage_shop', 'shop-reports', fn () => [
            'component' => 'simple-shop/Reports',
            'props' => ['ordersUrl' => admin_page_url('shop-orders')],
        ]);
        add_submenu_page('shop', 'Settings', 'Settings', 'manage_options', admin_url('settings/shop'));

        // A page under Tools (requirement: extend any core menu).
        add_management_page('Shop Tools', 'Shop Tools', 'manage_shop', 'shop-tools', [static::class, 'toolsPage']);

        // Badge with today's new orders.
        add_filter('admin_menu_items', function (array $items) {
            $count = Order::whereDate('created_at', today())->count();
            foreach ($items as &$item) {
                if ($item['slug'] === 'shop' && $count) {
                    $item['badge'] = $count;
                }
            }

            return $items;
        });
    }

    public static function settings(): void
    {
        // Appears automatically under Settings → Shop.
        register_settings_page('shop', [
            'title' => 'Shop Settings',
            'menu_title' => 'Shop',
            'capability' => 'manage_shop',
            'position' => 90,
            'sections' => [
                ['id' => 'currency', 'title' => 'Currency', 'fields' => [
                    ['name' => 'shop_currency', 'label' => 'Currency', 'type' => 'select', 'default' => 'USD', 'choices' => [
                        'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'Pound sterling (£)', 'PHP' => 'Philippine peso (₱)', 'JPY' => 'Japanese yen (¥)', 'AUD' => 'Australian dollar (A$)',
                    ]],
                    ['name' => 'shop_currency_position', 'label' => 'Currency position', 'type' => 'radio', 'default' => 'left', 'choices' => ['left' => 'Left ($99.00)', 'right' => 'Right (99.00 $)']],
                ]],
                ['id' => 'pages', 'title' => 'Pages', 'fields' => [
                    ['name' => 'shop_cart_page', 'label' => 'Cart page', 'type' => 'page', 'description' => 'Must contain the [cart] shortcode.',
                        'choices' => fn () => ['0' => '— Select —'] + Post::where('type', 'page')->pluck('title', 'id')->all()],
                    ['name' => 'shop_checkout_page', 'label' => 'Checkout page', 'type' => 'page', 'description' => 'Must contain the [checkout] shortcode.',
                        'choices' => fn () => ['0' => '— Select —'] + Post::where('type', 'page')->pluck('title', 'id')->all()],
                ]],
                ['id' => 'checkout', 'title' => 'Checkout', 'fields' => [
                    ['name' => 'shop_guest_checkout', 'label' => 'Guest checkout', 'type' => 'toggle', 'default' => true, 'description' => 'Allow customers to place orders without an account'],
                    ['name' => 'shop_payment_instructions', 'label' => 'Payment instructions', 'type' => 'textarea', 'default' => 'Pay with cash on delivery.'],
                    ['name' => 'shop_order_email', 'label' => 'New order email', 'type' => 'email', 'rules' => 'nullable|email'],
                    ['name' => 'shop_low_stock', 'label' => 'Low stock threshold', 'type' => 'number', 'default' => 2, 'width' => 'small'],
                ]],
            ],
        ]);

        // Example of extending a CORE settings page from a plugin.
        add_settings_field('general', 'site', [
            'name' => 'shop_store_address', 'label' => 'Store address', 'type' => 'textarea',
            'description' => 'Added to Settings → General by the Simple Shop plugin.',
        ]);
    }

    public static function tool(): void
    {
        register_tool('shop-tools', [
            'title' => 'Shop Tools',
            'description' => 'Export orders to CSV and audit low-stock products.',
            'icon' => 'shopping-cart',
            'capability' => 'manage_shop',
            'url' => admin_page_url('shop-tools'),
            'action_label' => 'Open shop tools',
        ]);
    }

    /* ---------------------------------------------------------------- pages */

    public static function overviewPage(): string
    {
        $revenue = (float) Order::whereNotIn('status', ['cancelled', 'refunded'])->sum('total');
        $orders = Order::count();
        $products = Post::where('type', 'product')->where('status', 'publish')->count();
        $processing = Order::where('status', 'processing')->count();

        $stat = fn ($label, $value) => '<div class="pm-card"><div class="pm-muted">'.e($label).'</div><div class="pm-stat">'.e($value).'</div></div>';

        $html = '<p>Welcome to <strong>Simple Shop</strong>. This page is a plain PHP callback returning HTML — the simplest way to add an admin screen.</p>';
        $html .= '<div class="pm-grid">'.$stat('Revenue', Shop::money($revenue)).$stat('Orders', (string) $orders).$stat('Awaiting fulfilment', (string) $processing).$stat('Products', (string) $products).'</div>';
        $html .= '<h2>Quick links</h2><p>'
            .'<a class="button button-primary" href="'.e(admin_url('content/product/create')).'">Add product</a> '
            .'<a class="button" href="'.e(admin_page_url('shop-orders')).'">View orders</a> '
            .'<a class="button" href="'.e(admin_page_url('shop-reports')).'">Reports</a> '
            .'<a class="button" href="'.e(admin_url('settings/shop')).'">Settings</a></p>';
        $html .= '<h2>Shortcodes</h2><table class="widefat"><tbody>'
            .'<tr><td><code>[products limit="8" category="apparel" columns="4"]</code></td><td>Product grid</td></tr>'
            .'<tr><td><code>[add_to_cart id="12"]</code></td><td>Price + add-to-cart button</td></tr>'
            .'<tr><td><code>[cart]</code> / <code>[checkout]</code></td><td>Cart and checkout pages</td></tr>'
            .'<tr><td><code>[cart_count]</code></td><td>Number of items in the cart</td></tr></tbody></table>';

        return $html;
    }

    public static function ordersPage(Request $request): string
    {
        $status = $request->query('status');
        $orders = Order::when($status, fn ($q) => $q->where('status', $status))->latest()->paginate(20)->withQueryString();
        $counts = Order::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        $html = '<p>';
        $html .= '<a href="'.e(admin_page_url('shop-orders')).'">All ('.$counts->sum().')</a>';
        foreach (Order::STATUSES as $key => $label) {
            $html .= ' | <a href="'.e(admin_page_url('shop-orders', ['status' => $key])).'">'.e($label).' ('.($counts[$key] ?? 0).')</a>';
        }
        $html .= '</p>';

        if ($orders->isEmpty()) {
            return $html.'<div class="notice"><p>No orders yet. Place one from the <a href="'.e(Shop::pageUrl('cart')).'">store front</a>.</p></div>';
        }

        $html .= '<table class="widefat"><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead><tbody>';
        foreach ($orders as $o) {
            $items = collect($o->items)->map(fn ($i) => e($i['title']).' × '.(int) $i['qty'])->implode('<br>');
            $select = '<form method="post" action="'.e(admin_url('admin-post')).'" style="display:flex;gap:6px">'.csrf_field()
                .'<input type="hidden" name="action" value="shop_update_order"><input type="hidden" name="order_id" value="'.$o->id.'">'
                .'<select name="status" style="min-width:0">';
            foreach (Order::STATUSES as $key => $label) {
                $select .= '<option value="'.$key.'"'.selected($o->status, $key).'>'.e($label).'</option>';
            }
            $select .= '</select><button type="submit" class="button">Save</button></form>';

            $html .= '<tr><td><strong>#'.e($o->number).'</strong></td><td>'.e(format_cms_date($o->created_at, 'M j, Y g:i a')).'</td>'
                .'<td>'.e($o->customer_name).'<br><span class="pm-muted">'.e($o->email).'</span><br><span class="pm-muted">'.nl2br(e((string) $o->address)).'</span></td>'
                .'<td>'.$items.'</td><td>'.e(Shop::money((float) $o->total)).'</td><td>'.$select.'</td></tr>';
        }
        $html .= '</tbody></table>';

        if ($orders->hasPages()) {
            $html .= '<p>'.($orders->previousPageUrl() ? '<a class="button" href="'.e($orders->previousPageUrl()).'">← Newer</a> ' : '')
                .($orders->nextPageUrl() ? '<a class="button" href="'.e($orders->nextPageUrl()).'">Older →</a>' : '').'</p>';
        }

        return $html;
    }

    public static function toolsPage(): string
    {
        $low = Post::where('type', 'product')->get()->filter(fn ($p) => $p->getMeta('_manage_stock') && (int) $p->getMeta('_stock') <= (int) get_option('shop_low_stock', 2));

        $html = '<div class="pm-card"><h2>Export orders</h2><p>Download every order as a CSV file.</p>'
            .'<form method="post" action="'.e(admin_url('admin-post')).'" data-cms-native>'.csrf_field()
            .'<input type="hidden" name="action" value="shop_export_orders"><button type="submit" class="button button-primary">Download CSV</button></form></div>';

        $html .= '<div class="pm-card"><h2>Low stock</h2>';
        if ($low->isEmpty()) {
            $html .= '<p>All good — no products at or below the low-stock threshold.</p>';
        } else {
            $html .= '<table class="widefat"><thead><tr><th>Product</th><th>Stock</th></tr></thead><tbody>';
            foreach ($low as $p) {
                $html .= '<tr><td><a href="'.e(admin_url('content/product/'.$p->id.'/edit')).'">'.e($p->title).'</a></td><td>'.(int) $p->getMeta('_stock').'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html.'</div>';
    }

    public static function dashboardWidget(): string
    {
        $today = Order::whereDate('created_at', today())->whereNotIn('status', ['cancelled', 'refunded']);
        $week = Order::where('created_at', '>=', now()->subDays(7))->whereNotIn('status', ['cancelled', 'refunded']);

        return '<div class="pm-grid" style="grid-template-columns:1fr 1fr">'
            .'<div><div class="pm-muted">Today</div><div class="pm-stat">'.e(Shop::money((float) $today->sum('total'))).'</div><div class="pm-muted">'.$today->count().' orders</div></div>'
            .'<div><div class="pm-muted">Last 7 days</div><div class="pm-stat">'.e(Shop::money((float) $week->sum('total'))).'</div><div class="pm-muted">'.$week->count().' orders</div></div>'
            .'</div><p><a href="'.e(admin_page_url('shop-orders')).'">View all orders →</a></p>';
    }
}
