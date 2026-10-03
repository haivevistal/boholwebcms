<?php

namespace App\Cms\Core;

use App\Models\Media;
use App\Models\Post;
use App\Models\Term;

/**
 * Built-in content: post types, taxonomies, content filters, core
 * shortcodes and scheduled publishing. Registered with the public API, so
 * plugins can modify any of it with the usual hooks.
 */
class CoreContent
{
    public function register(): void
    {
        register_post_type('post', [
            'label' => 'Posts',
            'labels' => ['singular_name' => 'Post', 'all_items' => 'All Posts', 'add_new' => 'Add Post', 'menu_name' => 'Posts'],
            'public' => true,
            'menu_icon' => 'pin',
            'menu_position' => 5,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'],
            'taxonomies' => ['category', 'post_tag'],
            'capability_type' => 'post',
            'builtin' => true,
        ]);

        register_post_type('page', [
            'label' => 'Pages',
            'labels' => ['singular_name' => 'Page', 'all_items' => 'All Pages', 'add_new' => 'Add Page', 'menu_name' => 'Pages'],
            'public' => true,
            'hierarchical' => true,
            'menu_icon' => 'file',
            'menu_position' => 20,
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'page-attributes', 'comments', 'custom-fields'],
            'capability_type' => 'page',
            'builtin' => true,
        ]);

        register_taxonomy('category', 'post', [
            'label' => 'Categories',
            'labels' => ['singular_name' => 'Category'],
            'hierarchical' => true,
            'builtin' => true,
        ]);

        register_taxonomy('post_tag', 'post', [
            'label' => 'Tags',
            'labels' => ['singular_name' => 'Tag'],
            'hierarchical' => false,
            'builtin' => true,
        ]);

        // Content pipeline (same priorities as WordPress).
        add_filter('the_content', 'do_shortcode', 11);
        add_filter('the_content', [$this, 'lazyLoadImages'], 20);
        add_filter('get_the_excerpt', fn ($excerpt) => strip_shortcodes((string) $excerpt), 5);

        $this->shortcodes();

        // Scheduled posts.
        add_action('cms_cron', [$this, 'publishScheduled']);
    }

    public function lazyLoadImages(string $html): string
    {
        return preg_replace('/<img(?![^>]*\bloading=)/i', '<img loading="lazy"', $html) ?? $html;
    }

    public function publishScheduled(): void
    {
        Post::where('status', 'future')->where('published_at', '<=', now())->get()->each(function (Post $post) {
            $post->status = 'publish';
            $post->save();
            do_action('transition_post_status', 'publish', 'future', $post);
            do_action('publish_future_post', $post);
        });
    }

    protected function shortcodes(): void
    {
        add_shortcode('site_title', fn () => e(get_bloginfo('name')));

        add_shortcode('year', fn () => date('Y'));

        // [button url="/contact" style="primary" target="_blank"]Contact us[/button]
        add_shortcode('button', function ($atts, $content) {
            $a = shortcode_atts(['url' => '#', 'style' => 'primary', 'target' => '', 'text' => ''], $atts, 'button');
            $label = $content !== null ? do_shortcode($content) : e($a['text'] ?: 'Click here');
            $target = $a['target'] ? ' target="'.e($a['target']).'" rel="noopener"' : '';

            return '<a class="cms-button cms-button--'.e($a['style']).'" href="'.esc_url($a['url']).'"'.$target.'>'.$label.'</a>';
        });

        // [gallery ids="4,5,6" columns="3" size="medium"]
        add_shortcode('gallery', function ($atts) {
            $a = shortcode_atts(['ids' => '', 'columns' => 3, 'size' => 'medium', 'link' => 'file'], $atts, 'gallery');
            $ids = array_filter(array_map('intval', explode(',', (string) $a['ids'])));
            if (! $ids) {
                return '';
            }
            $items = Media::whereIn('id', $ids)->get()->sortBy(fn ($m) => array_search($m->id, $ids));
            $cols = max(1, min(8, (int) $a['columns']));
            $html = '<div class="cms-gallery" style="display:grid;gap:12px;grid-template-columns:repeat('.$cols.',minmax(0,1fr))">';
            foreach ($items as $m) {
                $img = '<img src="'.e($m->sizeUrl($a['size'])).'" alt="'.e($m->alt ?: $m->title).'" style="width:100%;height:100%;object-fit:cover;aspect-ratio:1">';
                $html .= '<figure class="cms-gallery__item" style="margin:0">'.($a['link'] === 'file' ? '<a href="'.e($m->url).'">'.$img.'</a>' : $img).'</figure>';
            }

            return $html.'</div>';
        });

        // [recent_posts count="5" type="post" category="news" excerpt="true"]
        add_shortcode('recent_posts', function ($atts) {
            $a = shortcode_atts(['count' => 5, 'type' => 'post', 'category' => '', 'excerpt' => 'false'], $atts, 'recent_posts');
            $args = ['type' => $a['type'], 'limit' => (int) $a['count']];
            if ($a['category'] !== '') {
                $args['term'] = explode(',', $a['category']);
                $args['taxonomy'] = 'category';
            }
            $posts = get_posts($args);
            if ($posts->isEmpty()) {
                return '';
            }
            $html = '<ul class="cms-recent-posts">';
            foreach ($posts as $post) {
                $html .= '<li><a href="'.e($post->permalink).'">'.e($post->title).'</a>';
                if (filter_var($a['excerpt'], FILTER_VALIDATE_BOOLEAN)) {
                    $html .= '<p>'.e($post->excerptText(25)).'</p>';
                }
                $html .= '</li>';
            }

            return $html.'</ul>';
        });

        // [categories] — list of categories with post counts
        add_shortcode('categories', function ($atts) {
            $a = shortcode_atts(['taxonomy' => 'category', 'count' => 'true'], $atts, 'categories');
            $terms = Term::where('taxonomy', $a['taxonomy'])->orderBy('name')->get();
            $html = '<ul class="cms-term-list">';
            foreach ($terms as $t) {
                $html .= '<li><a href="'.e($t->link).'">'.e($t->name).'</a>'.(filter_var($a['count'], FILTER_VALIDATE_BOOLEAN) ? ' <span>('.$t->count.')</span>' : '').'</li>';
            }

            return $html.'</ul>';
        });

        // [login_form redirect="/"]
        add_shortcode('login_form', function ($atts) {
            if (is_user_logged_in()) {
                return '<p class="cms-logged-in">You are logged in as '.e(current_user()->name).'. <a href="'.e(route('logout.get', ['_token' => csrf_token()])).'">Log out</a></p>';
            }
            $a = shortcode_atts(['redirect' => ''], $atts, 'login_form');

            return '<form class="cms-login-form" method="post" action="'.e(route('login')).'">'
                .csrf_field()
                .'<input type="hidden" name="redirect_to" value="'.e($a['redirect']).'">'
                .'<p><label>Username or Email<br><input name="login" required></label></p>'
                .'<p><label>Password<br><input type="password" name="password" required></label></p>'
                .'<p><label><input type="checkbox" name="remember" value="1"> Remember me</label></p>'
                .'<p><button type="submit" class="cms-button cms-button--primary">Log in</button></p></form>';
        });
    }
}
