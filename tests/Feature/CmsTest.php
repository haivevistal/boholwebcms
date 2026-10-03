<?php

namespace Tests\Feature;

use App\Cms\Extensions\PluginManager;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->installCms();
    }

    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_dashboard_has_the_default_navigation(): void
    {
        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('adminMenu', fn ($menu) => collect($menu)->pluck('slug')->values()->all() === [
                    'dashboard', 'posts', 'comments', 'media', 'pages', 'appearance', 'plugins', 'users', 'tools', 'settings',
                ]));
    }

    public function test_plugins_can_extend_any_menu(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page('tools', 'Cache', 'Cache', 'manage_options', 'cache', fn () => '<p>Cache tool</p>');
            add_options_page('Mine', 'Mine', 'manage_options', 'mine', fn () => ['component' => 'me/Settings']);
        });

        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('adminMenu', function ($menu) {
                $tools = collect($menu)->firstWhere('slug', 'tools');
                $settings = collect($menu)->firstWhere('slug', 'settings');

                return collect($tools['children'])->contains('slug', 'cache') && collect($settings['children'])->contains('slug', 'mine');
            }));

        $this->actingAs($this->admin)->get('/admin/page/cache')
            ->assertInertia(fn (Assert $page) => $page->component('PluginPage')->where('page.html', '<p>Cache tool</p>'));
    }

    public function test_creating_a_post_and_viewing_it_through_the_theme(): void
    {
        $this->actingAs($this->admin)->post('/admin/content/post', [
            'title' => 'Hello BoholwebCMS',
            'content' => '<p>[button url="/x"]Go[/button]</p>',
            'status' => 'publish',
            'comment_status' => 'open',
        ])->assertRedirect();

        $post = Post::where('slug', 'hello-boholwebcms')->firstOrFail();
        $this->assertSame('/hello-boholwebcms', parse_url($post->permalink, PHP_URL_PATH));

        $this->get('/hello-boholwebcms')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Front/Theme')
            ->where('view.kind', 'single')
            ->where('view.templates', ['single-post-hello-boholwebcms', 'single-post', 'single', 'singular', 'index'])
            ->where('view.post.content', fn ($html) => str_contains($html, 'class="cms-button'))
            ->where('theme.slug', 'aurora'));
    }

    public function test_pages_use_hierarchical_urls_and_unknown_urls_404(): void
    {
        $parent = cms_insert_post(['type' => 'page', 'status' => 'publish', 'title' => 'About']);
        cms_insert_post(['type' => 'page', 'status' => 'publish', 'title' => 'Team', 'parent_id' => $parent->id]);

        $this->get('/about/team')->assertOk()->assertInertia(fn (Assert $page) => $page->where('view.kind', 'page')->where('view.post.title', 'Team'));
        $this->get('/does-not-exist')->assertNotFound();
    }

    public function test_settings_pages_save_options(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/general', ['values' => ['blogname' => 'Renamed', 'admin_email' => 'a@b.co']])
            ->assertSessionHas('success');

        $this->assertSame('Renamed', get_option('blogname'));
    }

    public function test_switching_themes(): void
    {
        $this->actingAs($this->admin)->post('/admin/themes/minimal/activate')->assertSessionHas('success');
        $this->assertSame('minimal', get_option('active_theme'));
    }

    public function test_activating_the_shop_plugin_registers_its_features(): void
    {
        $key = 'simple-shop/simple-shop.php';
        app(PluginManager::class)->activate($key);

        $this->assertContains($key, get_option('active_plugins'));
        $this->assertNotNull(get_option('shop_cart_page'));

        do_action('init'); // the plugin hooks into init; simulate the next request
        $this->assertTrue(post_type_exists('product'));
        $this->assertTrue(shortcode_exists('products'));
    }

    public function test_comment_submission_respects_moderation(): void
    {
        update_option('comment_moderation', true);
        $post = cms_insert_post(['type' => 'post', 'status' => 'publish', 'title' => 'C', 'comment_status' => 'open']);

        $this->post('/comments', ['post_id' => $post->id, 'content' => 'Nice', 'author_name' => 'Ann', 'author_email' => 'ann@example.com'])
            ->assertRedirect();

        $this->assertSame('pending', $post->comments()->first()->status);
    }

    public function test_subscribers_cannot_manage_options(): void
    {
        $sub = User::factory()->create(['role' => 'subscriber']);
        $this->actingAs($sub)->get('/admin/settings/general')->assertForbidden();
        $this->actingAs($sub)->get('/admin')->assertRedirect('/admin/profile');
    }

    public function test_web_installer_is_locked_after_installation(): void
    {
        $this->get('/install')->assertRedirect('/admin');
        $this->postJson('/install/run', [])->assertForbidden();
    }
}
