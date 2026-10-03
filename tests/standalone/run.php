<?php
/**
 * Framework-free smoke tests for the CMS core. Runs with plain PHP:
 *   php tests/standalone/run.php
 * Laravel helpers are stubbed just enough to exercise the core classes.
 * (The full PHPUnit suite in tests/Feature needs `composer install`.)
 */

$base = dirname(__DIR__, 2);
spl_autoload_register(function ($class) use ($base) {
    if (str_starts_with($class, 'App\\')) {
        $file = $base.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($file)) require $file;
    }
});

// ---- minimal framework stubs ------------------------------------------------
$GLOBALS['__c'] = [];
$GLOBALS['__options'] = ['active_plugins' => [], 'active_theme' => 'aurora'];
function app($abstract = null) {
    if ($abstract === null) return new class { function bound($a) { return isset($GLOBALS['__c'][$a]) || class_exists($a); } function instance($a, $v) { $GLOBALS['__c'][$a] = $v; } };
    if (! isset($GLOBALS['__c'][$abstract])) {
        $GLOBALS['__c'][$abstract] = match ($abstract) {
            App\Cms\Shortcodes\ShortcodeManager::class => new App\Cms\Shortcodes\ShortcodeManager(fn ($h, $v, ...$a) => apply_filters($h, $v, ...$a)),
            App\Cms\Extensions\PluginManager::class => new App\Cms\Extensions\PluginManager($GLOBALS['base'].'/content/plugins'),
            App\Cms\Extensions\ThemeManager::class => new App\Cms\Extensions\ThemeManager($GLOBALS['base'].'/content/themes'),
            default => new $abstract,
        };
    }
    return $GLOBALS['__c'][$abstract];
}
$GLOBALS['base'] = $base;
function url($p = '') { return 'http://localhost'.($p === '' || $p === '/' ? '' : '/'.ltrim($p, '/')); }
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function config($k, $d = null) { return ['cms.content_url_prefix' => 'content', 'cms.plugins_path' => $GLOBALS['base'].'/content/plugins', 'cms.default_theme' => 'aurora', 'cms.disallow_file_edit' => false, 'cms.disallow_file_mods' => false, 'cms.editable_extensions' => ['php', 'json', 'js', 'jsx', 'css', 'md']][$k] ?? $d; }
function collect($items = []) { return new class($items) { function __construct(public $i) {} function contains($k, $v) { foreach ($this->i as $x) if (($x[$k] ?? null) === $v) return true; return false; } }; }
function get_option($n, $d = null) { return array_key_exists($n, $GLOBALS['__options']) ? $GLOBALS['__options'][$n] : $d; }
function update_option($n, $v) { $GLOBALS['__options'][$n] = $v; return true; }
function current_user_can($cap, ...$a) { return $cap !== 'nope'; }
function csrf_field() { return '<input type="hidden" name="_token" value="x">'; }

require $base.'/app/Cms/helpers.php';

// ---- tiny assertion helper ---------------------------------------------------
$pass = 0; $fail = 0;
function check(string $label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "  ✓ {$label}\n"; return; }
    $fail++; echo "  ✗ {$label}\n      expected: ".var_export($expected, true)."\n      actual:   ".var_export($actual, true)."\n";
}

echo "Hooks\n";
add_filter('title', fn ($t) => "<$t>", 20);
add_filter('title', 'strtoupper', 10);
add_filter('title', fn ($t, $suffix) => $t.$suffix, 5, 2);
check('priorities + accepted args + internal functions', apply_filters('title', 'hi', '!'), '<HI!>');
$log = [];
add_action('saved', function ($id, $update) use (&$log) { $log[] = [$id, $update]; });
do_action('saved', 7, true);
check('action receives args', $log, [[7, true]]);
check('did_action', did_action('saved'), 1);
$cb = fn ($v) => $v * 2;
add_filter('num', $cb);
check('has_filter returns priority', has_filter('num', $cb), 10);
remove_filter('num', $cb);
check('remove_filter', apply_filters('num', 3), 3);
add_filter('nested', function ($v) { return apply_filters('inner', $v).current_filter(); });
add_filter('inner', fn ($v) => $v.'+');
check('nested filters / current_filter', apply_filters('nested', 'x'), 'x+nested');

echo "Shortcodes\n";
add_shortcode('hello', fn ($a, $c) => 'Hello '.($a['name'] ?? 'world').($c ? " [$c]" : ''));
add_shortcode('wrap', fn ($a, $c) => '<div>'.do_shortcode($c).'</div>');
check('self-closing + attrs', do_shortcode('[hello name="Haive"]'), 'Hello Haive');
check('enclosing + nesting', do_shortcode('[wrap][hello name=Bob]x[/hello][/wrap]'), '<div>Hello Bob [x]</div>');
check('escaped shortcode', do_shortcode('[[hello]]'), '[hello]');
check('unknown shortcode untouched', do_shortcode('[nope] [hello]'), '[nope] Hello world');
check('<p> unwrapping', do_shortcode('<p>[wrap]a[/wrap]</p>'), '<div>a</div>');
check('entity-encoded quotes from editors', do_shortcode('<p>[hello name=&quot;Ann&quot;]</p>'), 'Hello Ann');
check('shortcode_atts', shortcode_atts(['a' => 1, 'b' => 2], ['b' => 3, 'c' => 4]), ['a' => 1, 'b' => 3]);
check('strip_shortcodes', strip_shortcodes('a [hello] b'), 'a  b');
check('has_shortcode', has_shortcode('x [wrap]y[/wrap]', 'wrap'), true);

echo "File headers\n";
$h = App\Cms\Support\FileHeader::parse("<?php\n/**\n * Plugin Name: Demo Thing\n * Version: 2.1\n * Description: Does stuff */", App\Cms\Support\FileHeader::PLUGIN_HEADERS);
check('plugin name', $h['name'], 'Demo Thing');
check('version', $h['version'], '2.1');
check('description strips */', $h['description'], 'Does stuff');

echo "Plugin discovery\n";
$plugins = app(App\Cms\Extensions\PluginManager::class)->all();
check('simple-shop discovered', isset($plugins['simple-shop/simple-shop.php']), true);
check('plugin metadata', $plugins['simple-shop/simple-shop.php']['name'] ?? null, 'Simple Shop');
check('migrations detected', $plugins['simple-shop/simple-shop.php']['has_migrations'] ?? null, true);
check('keyForFile', app(App\Cms\Extensions\PluginManager::class)->keyForFile($base.'/content/plugins/simple-shop/src/Shop.php'), 'simple-shop/simple-shop.php');

echo "Themes\n";
$tm = app(App\Cms\Extensions\ThemeManager::class);
check('three themes', array_keys($tm->all()), ['aurora', 'aurora-child', 'minimal']);
$GLOBALS['__options']['active_theme'] = 'aurora-child';
check('child theme stack (parent first)', array_column($tm->stack(), 'slug'), ['aurora', 'aurora-child']);
$mods = $tm->mods();
check('mods merge parent + child defaults', [$mods['accent_color'], $mods['promo_text'] !== null], ['#4f46e5', true]);
check('page templates inherited', array_keys($tm->pageTemplates()), ['full-width', 'landing']);
$GLOBALS['__options']['theme_mods_aurora-child'] = ['accent_color' => '#ff0000'];
check('saved mod overrides default', $tm->mods()['accent_color'], '#ff0000');
$GLOBALS['__options']['active_theme'] = 'aurora';

echo "Admin menu\n";
$menu = app(App\Cms\Admin\AdminMenu::class);
add_action('_core_admin_menu', function ($m) {
    $m->addMenuPage('Tools', 'Tools', 'read', 'tools', null, 'wrench', 75);
    $m->addSubmenuPage('tools', 'Available Tools', 'Available Tools', 'read', 'available-tools', null, 10, ['url' => url('/admin/tools')]);
    $m->addMenuPage('Settings', 'Settings', 'read', 'settings', null, 'settings', 80);
    $m->addSubmenuPage('settings', 'General', 'General', 'read', 'settings-general', null, 10, ['url' => url('/admin/settings/general')]);
});
add_action('admin_menu', function () {
    add_management_page('Cache', 'Cache Tool', 'read', 'cache-tool', fn () => '<p>hi</p>');
    add_options_page('My Settings', 'My Plugin', 'read', 'my-plugin', fn () => 'x', 5);
    add_menu_page('Shop', 'Shop', 'read', 'shop', fn () => 'overview', 'store', 26);
    add_submenu_page('shop', 'Orders', 'Orders', 'read', 'orders', fn () => 'orders');
    add_menu_page('Secret', 'Secret', 'nope', 'secret', fn () => '');
    remove_submenu_page('tools', 'available-tools');
});
$tree = $menu->toArray();
$bySlug = array_column($tree, null, 'slug');
check('menus sorted by position', array_column($tree, 'slug'), ['shop', 'tools', 'settings']);
check('capability hides menu', isset($bySlug['secret']), false);
check('plugin page added under Tools; core item removed', array_column($bySlug['tools']['children'], 'slug'), ['cache-tool']);
check('plugin page under Settings respects position', array_column($bySlug['settings']['children'], 'slug'), ['my-plugin', 'settings-general']);
check('top-level page becomes first child', array_column($bySlug['shop']['children'], 'slug'), ['shop', 'orders']);
check('callback pages served at /admin/page/{slug}', $bySlug['tools']['children'][0]['url'], 'http://localhost/admin/page/cache-tool');
check('page registry', $menu->page('orders')['parent'], 'shop');
check('capability stripped from client payload', array_key_exists('capability', $tree[0]), false);

echo "Settings registry\n";
register_settings_page('shop', ['title' => 'Shop', 'sections' => [['id' => 'main', 'fields' => [['name' => 'shop_currency', 'type' => 'select', 'default' => 'USD', 'choices' => fn () => ['USD' => 'Dollar']]]]]]);
add_settings_field('shop', 'main', ['name' => 'shop_extra', 'label' => 'Extra']);
add_settings_field('general', 'site', ['name' => 'company_phone']);
$resolved = app(App\Cms\Admin\SettingsRegistry::class)->resolve('shop');
check('lazy choices resolved', $resolved['sections'][0]['fields'][0]['choices'], ['USD' => 'Dollar']);
check('values default', $resolved['values'], ['shop_currency' => 'USD', 'shop_extra' => null]);
check('field added to core page', array_keys(app(App\Cms\Admin\SettingsRegistry::class)->fields('general')), ['company_phone']);

echo "Assets\n";
enqueue_style('a', '/a.css');
enqueue_script('b', '/b.js', ['c']);
enqueue_script('c', '/c.js', [], '1.2');
localize_script('b', 'BConf', ['x' => 1]);
$footer = app(App\Cms\Support\Assets::class)->renderFooter();
check('deps ordered first', strpos($footer, 'c.js') < strpos($footer, 'b.js'), true);
check('versioned src', str_contains($footer, '/c.js?ver=1.2'), true);
check('localized data before script', strpos($footer, 'BConf') < strpos($footer, 'b.js'), true);
check('content url', plugin_url($base.'/content/plugins/simple-shop/simple-shop.php', 'assets/shop.js'), 'http://localhost/content/plugins/simple-shop/assets/shop.js');

echo "HTML sanitizer\n";
$clean = kses_post('<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:bad()">l</a> <b>b</b><custom>t</custom><img src="/a.png" onerror="x"></p>');
check('scripts/handlers/js urls removed, unknown unwrapped', $clean, '<p>Hi <a>l</a> <b>b</b>t<img src="/a.png"></p>');

echo "File editor\n";
$fe = app(App\Cms\Extensions\FileEditor::class);
try { $fe->lint('<?php echo "ok";'); check('valid php passes lint', true, true); } catch (Throwable $e) { check('valid php passes lint', false, true); }
try { $fe->lint('<?php echo ;'); check('invalid php rejected', false, true); } catch (RuntimeException $e) { check('invalid php rejected', str_contains($e->getMessage(), 'syntax error'), true); }
try { $fe->read($base.'/content/themes/aurora', '../../../.env.example'); check('path traversal blocked', false, true); } catch (RuntimeException $e) { check('path traversal blocked', $e->getMessage(), 'Invalid file path.'); }
if (class_exists(Symfony\Component\Finder\Finder::class)) check('theme file list includes functions.php', in_array('functions.php', array_column($fe->files($base.'/content/themes/aurora'), 'path'), true), true);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
