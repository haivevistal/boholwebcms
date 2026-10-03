# Simple Shop (example plugin)

A small but complete store that demonstrates every BoholwebCMS extension point:

| Feature | API used |
|---|---|
| Products & categories | `register_post_type()`, `register_taxonomy()` |
| Price / SKU / stock fields | `add_meta_box()` (declarative fields) + an HTML meta box handled on `save_post_product` |
| Product grid, cart, checkout | `add_shortcode()` — `[products]`, `[add_to_cart]`, `[cart]`, `[checkout]`, `[cart_count]` |
| Cart & checkout endpoints | `add_action('cms_routes', fn ($router) => …)` |
| AJAX add to cart | `add_action('ajax_nopriv_shop_add_to_cart')` + `CMS.ajax()` in `assets/shop.js` |
| Price box on product pages | `add_filter('the_content')` |
| "Cart (n)" menu item | `add_filter('cms_nav_menu')` |
| Shop admin menu (+ badge) | `add_menu_page()`, `add_submenu_page()`, `add_filter('admin_menu_items')` |
| Page under Tools | `add_management_page()` + `register_tool()` |
| Settings → Shop | `register_settings_page()`; adds a field to Settings → General with `add_settings_field()` |
| React reports screen | `['component' => 'simple-shop/Reports']` + `CMS.registerAdminComponent()` |
| Orders table | plugin migration in `migrations/` (runs on activation) |
| Status updates, CSV export | `admin_post_{action}` handlers |
| Dashboard widget | `add_dashboard_widget()` |
| List-table columns | `manage_product_posts_columns` / `manage_product_posts_custom_column` |
| Shop Manager role, `manage_shop` cap | activation hook + `cms_capabilities` filter |
| Cleanup | `uninstall.php` |
