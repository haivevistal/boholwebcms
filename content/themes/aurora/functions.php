<?php
/**
 * Aurora theme functions — runs on every request while the theme is active,
 * exactly like a WordPress theme's functions.php.
 */

defined('CMS_LOADED') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('custom-logo');
    add_theme_support('post-thumbnails');
    add_theme_support('menus');
    add_image_size('aurora-card', 720, 450, true);
});

// Default sidebar "widgets". Plugins can add more with the same action,
// or replace them: remove_all_actions('cms_region_sidebar').
add_action('cms_region_sidebar', function () {
    echo '<section class="widget"><h3 class="widget-title">Recent Posts</h3>'.do_shortcode('[recent_posts count="5"]').'</section>';
    echo '<section class="widget"><h3 class="widget-title">Categories</h3>'.do_shortcode('[categories]').'</section>';
}, 20);

// Expose the theme accent colour to core shortcodes (.cms-button etc.).
add_action('cms_head', function () {
    $accent = get_theme_mod('accent_color', '#4f46e5');
    if (preg_match('/^#[0-9a-f]{3,8}$/i', (string) $accent)) {
        echo '<style>:root{--cms-accent:'.$accent.'}</style>';
    }
});

// Shorter excerpts on cards.
add_filter('get_the_excerpt', fn ($excerpt) => \Illuminate\Support\Str::words(strip_tags((string) $excerpt), 32, '…'), 20);
