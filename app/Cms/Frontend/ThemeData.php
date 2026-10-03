<?php

namespace App\Cms\Frontend;

use App\Cms\Extensions\ThemeManager;
use App\Cms\Support\Permalinks;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the props every theme receives, and the server-rendered <head> meta.
 *
 * Props (all filterable with "theme_props"):
 *   site     name, description, url, logo, icon, search/login/register/logout URLs
 *   theme    slug, stack (parent → child), mods (Customizer values)
 *   view     kind, templates, title, post, posts + pagination, archive, search_query
 *   menus    location => items           (filter: cms_nav_menu)
 *   regions  region => HTML               (action/filter: cms_region_{name})
 *   comments items, open, form settings   (single views only)
 *   adminBar shortcuts for logged-in editors
 */
class ThemeData
{
    public function __construct(
        protected ThemeManager $themes,
        protected Permalinks $permalinks,
    ) {}

    public function build(Request $request, array $context): array
    {
        $theme = $this->themes->active();
        $user = current_user();

        $props = [
            'site' => $this->site($user),
            'theme' => [
                'slug' => $this->themes->activeSlug(),
                'stack' => array_column($this->themes->stack(), 'slug'),
                'mods' => (object) $this->themes->mods(),
                'media' => (object) $this->modMedia(),
                'page_templates' => (object) $this->themes->pageTemplates(),
            ],
            'view' => $this->view($context),
            'menus' => $this->menus($theme, $context),
            'regions' => (object) $this->regions(),
            'comments' => $context['post'] instanceof Post && in_array($context['kind'], ['single', 'page', 'front_page'], true)
                ? $this->comments($context['post'])
                : null,
            'adminBar' => $this->adminBar($context),
            'privacy' => [
                'cookie_notice' => (bool) get_option('cookie_notice'),
                'cookie_notice_text' => (string) get_option('cookie_notice_text', ''),
                'policy_url' => ($id = (int) get_option('privacy_policy_page')) && ($p = Post::where('status', 'publish')->find($id)) ? $p->permalink : null,
            ],
            'customizePreview' => $request->boolean('customize_preview') && current_user_can('edit_theme_options'),
            'errors' => (object) [],
        ];

        return apply_filters('theme_props', $props, $context, $request);
    }

    /**
     * URLs for images picked in Customizer "media" controls, keyed by media id.
     */
    protected function modMedia(): array
    {
        $mods = $this->themes->mods();
        $ids = [];
        foreach ($this->themes->stack() as $theme) {
            foreach ($theme['customizer'] as $section) {
                foreach ($section['controls'] ?? [] as $control) {
                    if (($control['type'] ?? '') === 'media' && ! empty($mods[$control['id'] ?? ''])) {
                        $ids[] = (int) $mods[$control['id']];
                    }
                }
            }
        }

        return $ids ? Media::whereIn('id', $ids)->get()->mapWithKeys(fn ($m) => [$m->id => $m->url])->all() : [];
    }

    /**
     * Enqueue the active theme's bundle (and its parent's).
     */
    public function enqueueThemeAssets(): void
    {
        foreach ($this->themes->stack() as $theme) {
            $version = $theme['version'] ?: null;
            if ($theme['style'] && is_file($theme['dir'].'/'.$theme['style'])) {
                enqueue_style('theme-'.$theme['slug'], $this->themes->assetUrl($theme, $theme['style']), [], $version.'-'.@filemtime($theme['dir'].'/'.$theme['style']));
            }
            if ($theme['script'] && is_file($theme['dir'].'/'.$theme['script'])) {
                enqueue_script('theme-'.$theme['slug'], $this->themes->assetUrl($theme, $theme['script']), [], $version.'-'.@filemtime($theme['dir'].'/'.$theme['script']));
            }
        }

        if ($css = trim((string) get_theme_mod('custom_css', ''))) {
            add_inline_style(str_replace('</style', '', $css));
        }
    }

    /**
     * Server-rendered <head> tags (SEO works without SSR).
     */
    public function meta(array $context, array $props): array
    {
        $siteName = $props['site']['name'];
        $title = match (true) {
            $context['kind'] === 'home' || ($context['is_front_page'] ?? false) => $siteName.($props['site']['description'] ? ' – '.$props['site']['description'] : ''),
            default => trim(strip_tags((string) $context['title'])).' – '.$siteName,
        };

        $description = $props['site']['description'];
        $image = null;
        if ($context['post'] instanceof Post) {
            $description = cms_trim_words($context['post']->excerpt ?: (string) $context['post']->content, 30, '…');
            $image = $context['post']->featuredMedia?->url;
        }

        $canonical = $context['post'] instanceof Post ? $context['post']->permalink : url()->current();

        return apply_filters('document_meta', [
            'title' => apply_filters('document_title', $title, $context),
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $context['post'] instanceof Post ? 'article' : 'website',
            'robots' => get_option('discourage_search_engines') || $context['status'] === 404 ? 'noindex, nofollow' : null,
            'site_name' => $siteName,
            'language' => get_bloginfo('language'),
            'icon' => $props['site']['icon'],
        ], $context);
    }

    /* ------------------------------------------------------------------ */

    protected function site($user): array
    {
        $logoId = (int) get_theme_mod('custom_logo');
        $logo = $logoId ? Media::find($logoId) : null;
        $iconId = (int) get_option('site_icon');
        $icon = $iconId ? Media::find($iconId) : null;

        return [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => $this->permalinks->home(),
            'language' => get_bloginfo('language'),
            'logo' => $logo ? ['url' => $logo->url, 'alt' => $logo->alt ?: get_bloginfo('name'), 'width' => $logo->width, 'height' => $logo->height] : null,
            'icon' => $icon?->sizeUrl('thumbnail'),
            'show_title' => (bool) get_theme_mod('display_header_text', true),
            'search_url' => $this->permalinks->home(),
            'posts_url' => $this->permalinks->postsPage(),
            'login_url' => route('login'),
            'register_url' => get_option('users_can_register') ? route('register') : null,
            'logout_url' => $user ? route('logout.get', ['_token' => csrf_token()]) : null,
            'admin_url' => $user && current_user_can('read') ? url('/admin') : null,
            'ajax_url' => route('cms.ajax'),
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatarUrl(48)] : null,
            'date_format' => get_option('date_format', 'F j, Y'),
        ];
    }

    protected function view(array $context): array
    {
        $view = [
            'kind' => $context['kind'],
            'templates' => $context['templates'],
            'title' => $context['title'],
            'is_front_page' => (bool) $context['is_front_page'],
            'is_home' => (bool) $context['is_home'],
            'status' => $context['status'],
            'archive' => $context['archive'],
            'search_query' => $context['search_query'],
            'post' => $context['post'] instanceof Post ? $this->postWithNav($context['post']) : null,
            'page' => $context['page'] instanceof Post ? $context['page']->toFrontArray() : null,
            'posts' => null,
            'pagination' => null,
        ];

        if ($context['posts'] instanceof LengthAwarePaginator) {
            $full = get_option('excerpt_on_archives', 'excerpt') === 'full';
            $p = $context['posts'];
            $view['posts'] = collect($p->items())->map(fn (Post $post) => $post->toFrontArray($full))->all();

            $base = url()->current();
            $base = preg_replace('#/page/\d+$#', '', $base);
            $query = request()->except('paged');
            $baseWithQuery = $base.($query ? '?'.http_build_query($query) : '');

            $view['pagination'] = [
                'current' => $p->currentPage(),
                'last' => $p->lastPage(),
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'prev_url' => $p->currentPage() > 1 ? $this->permalinks->paged($baseWithQuery, $p->currentPage() - 1) : null,
                'next_url' => $p->hasMorePages() ? $this->permalinks->paged($baseWithQuery, $p->currentPage() + 1) : null,
                'pages' => collect(range(1, max(1, $p->lastPage())))->map(fn ($n) => [
                    'number' => $n,
                    'url' => $this->permalinks->paged($baseWithQuery, $n),
                    'current' => $n === $p->currentPage(),
                ])->all(),
            ];
        }

        return $view;
    }

    protected function postWithNav(Post $post): array
    {
        $data = $post->toFrontArray();

        if ($post->type !== 'page') {
            $date = $post->published_at ?? $post->created_at;
            $prev = Post::where('type', $post->type)->published()->where('published_at', '<', $date)->orderByDesc('published_at')->first(['id', 'type', 'title', 'slug', 'published_at', 'parent_id']);
            $next = Post::where('type', $post->type)->published()->where('published_at', '>', $date)->orderBy('published_at')->first(['id', 'type', 'title', 'slug', 'published_at', 'parent_id']);
            $data['previous'] = $prev ? ['title' => $prev->title, 'url' => $prev->permalink] : null;
            $data['next'] = $next ? ['title' => $next->title, 'url' => $next->permalink] : null;
        } else {
            $data['children'] = $post->children()->published()->orderBy('menu_order')->get(['id', 'type', 'title', 'slug', 'parent_id'])
                ->map(fn ($c) => ['title' => $c->title, 'url' => $c->permalink])->all();
        }

        if ($post->password && ! session()->get('post_password_'.$post->id)) {
            $data['password_form_action'] = route('post.unlock', $post->id);
        }

        return $data;
    }

    protected function menus(?array $theme, array $context): array
    {
        $current = rtrim(url()->current(), '/');
        $menus = [];

        foreach (array_keys($theme['menus'] ?? ['primary' => 'Primary']) as $location) {
            $items = $location === 'primary' ? $this->pageMenu() : [];
            if ($location === 'footer' && ($id = (int) get_option('privacy_policy_page')) && ($p = Post::where('status', 'publish')->find($id))) {
                $items[] = ['id' => 'privacy', 'title' => $p->title, 'url' => $p->permalink, 'children' => []];
            }
            $items = apply_filters('cms_nav_menu', $items, $location, $context);
            $menus[$location] = $this->markActive($items, $current);
        }

        return $menus;
    }

    protected function pageMenu(): array
    {
        $pages = Post::where('type', 'page')->published()->orderBy('menu_order')->orderBy('title')->get(['id', 'type', 'title', 'slug', 'parent_id', 'menu_order']);
        $frontId = get_option('show_on_front') === 'page' ? (int) get_option('page_on_front') : 0;
        $privacyId = (int) get_option('privacy_policy_page');

        $build = function ($parentId) use (&$build, $pages, $frontId, $privacyId) {
            return $pages->where('parent_id', $parentId)
                ->reject(fn ($p) => $p->id === $privacyId)
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'url' => $p->permalink,
                    'children' => $build($p->id),
                    'is_front_page' => $p->id === $frontId,
                ])->values()->all();
        };

        $items = $build(null);

        // Always offer a way home (unless a page already is the homepage).
        if (! $frontId) {
            array_unshift($items, ['id' => 'home', 'title' => 'Home', 'url' => $this->permalinks->home(), 'children' => []]);
        } else {
            usort($items, fn ($a, $b) => ($b['is_front_page'] ?? false) <=> ($a['is_front_page'] ?? false));
        }

        return $items;
    }

    protected function markActive(array $items, string $current): array
    {
        return array_map(function ($item) use ($current) {
            $item['children'] = $this->markActive($item['children'] ?? [], $current);
            $item['active'] = rtrim((string) $item['url'], '/') === $current
                || collect($item['children'])->contains(fn ($c) => $c['active'] ?? false);

            return $item;
        }, $items);
    }

    protected function regions(): array
    {
        $out = [];
        foreach (array_keys($this->themes->regions()) as $region) {
            $html = capture_action("cms_region_{$region}");
            $out[$region] = (string) apply_filters("cms_region_{$region}", $html);
        }

        return $out;
    }

    protected function comments(Post $post): array
    {
        $items = Comment::where('post_id', $post->id)
            ->where(function ($q) {
                $q->where('status', 'approved');
                // Visitors see their own pending comments.
                if ($ids = session('my_pending_comments', [])) {
                    $q->orWhereIn('id', $ids);
                }
            })
            ->with('user:id,name,email')
            ->orderBy('created_at')->get()
            ->map(fn (Comment $c) => $c->toFrontArray())->all();

        $open = $post->comment_status === 'open';
        if ($open && get_option('close_comments_for_old_posts') && $post->published_at) {
            $open = $post->published_at->diffInDays(now()) <= (int) get_option('close_comments_days_old', 14);
        }
        $open = (bool) apply_filters('comments_open', $open, $post);

        return [
            'items' => $items,
            'count' => count(array_filter($items, fn ($c) => $c['status'] === 'approved')),
            'open' => $open,
            'require_name_email' => (bool) get_option('require_name_email', true),
            'registration_required' => (bool) get_option('comment_registration'),
            'logged_in' => is_user_logged_in(),
            'threaded' => (bool) get_option('thread_comments', true),
            'max_depth' => (int) get_option('thread_comments_depth', 5),
            'show_avatars' => (bool) get_option('show_avatars', true),
            'action' => route('comments.store'),
            'post_id' => $post->id,
        ];
    }

    protected function adminBar(array $context): ?array
    {
        if (! is_user_logged_in() || ! current_user_can('edit_posts')) {
            return null;
        }

        $items = [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'url' => url('/admin')],
            ['id' => 'new-post', 'title' => '+ New', 'url' => url('/admin/content/post/create')],
        ];
        if ($context['post'] instanceof Post && current_user_can('edit_post', $context['post'])) {
            $type = app(\App\Cms\Content\PostTypeRegistry::class)->get($context['post']->type);
            $items[] = ['id' => 'edit', 'title' => 'Edit '.($type['labels']['singular_name'] ?? 'Post'), 'url' => url('/admin/content/'.$context['post']->type.'/'.$context['post']->id.'/edit')];
        }
        if (current_user_can('edit_theme_options')) {
            $items[] = ['id' => 'customize', 'title' => 'Customize', 'url' => url('/admin/customize')];
        }

        return ['items' => apply_filters('admin_bar_menu', $items, $context)];
    }
}
