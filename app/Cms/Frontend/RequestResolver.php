<?php

namespace App\Cms\Frontend;

use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Cms\Support\Permalinks;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Maps a front-end URL to a query + template hierarchy, like WordPress'
 * WP::parse_request() + WP_Query + template-loader.php.
 *
 * Plugins can take over any URL first:
 *   add_filter('cms_resolve_request', function ($context, string $path, Request $request) {
 *       if ($path === 'cart') return ['kind' => 'page', 'templates' => ['cart', 'page', 'index'], 'title' => 'Cart', ...];
 *       return $context;
 *   }, 10, 3);
 */
class RequestResolver
{
    public function __construct(
        protected Permalinks $permalinks,
        protected PostTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
    ) {}

    public function resolve(Request $request, string $path): array
    {
        $path = trim(urldecode($path), '/');
        $paged = max(1, (int) $request->query('paged', 1));

        if (preg_match('#^(.*?)/?page/(\d+)$#', $path, $m)) {
            $path = trim($m[1], '/');
            $paged = max(1, (int) $m[2]);
        }

        $context = apply_filters('cms_resolve_request', null, $path, $request, $paged);
        if (is_array($context)) {
            return $this->finalize($context);
        }

        // Preview of drafts for editors.
        if ($request->boolean('preview') && ($id = (int) $request->query('p', $request->query('page_id')))) {
            $post = Post::find($id);
            if ($post && current_user_can('edit_post', $post)) {
                return $this->finalize($this->singular($post));
            }
        }

        // Search: /?s=term
        if ($request->filled('s')) {
            return $this->finalize($this->search($request, (string) $request->query('s'), $paged));
        }

        // Plain (query string) permalinks work regardless of the structure.
        if ($context = $this->fromQueryString($request, $paged)) {
            return $this->finalize($context);
        }

        if ($path === '') {
            return $this->finalize($this->home($paged));
        }

        $segments = explode('/', $path);
        $first = $segments[0];

        // Category & tag archives.
        if ($first === $this->permalinks->categoryBase() && count($segments) > 1) {
            return $this->finalize($this->termArchive('category', end($segments), $paged));
        }
        if ($first === $this->permalinks->tagBase() && count($segments) > 1) {
            return $this->finalize($this->termArchive('post_tag', end($segments), $paged));
        }

        // Author archives.
        if ($first === 'author' && isset($segments[1])) {
            $author = User::where('username', $segments[1])->first();

            return $this->finalize($author ? $this->authorArchive($author, $paged) : $this->notFound());
        }

        // Custom taxonomies.
        if (($tax = $this->taxonomies->byRewriteSlug($first)) && count($segments) > 1) {
            return $this->finalize($this->termArchive($tax['name'], end($segments), $paged));
        }

        // Custom post types: /{slug} (archive) and /{slug}/{post}.
        if ($pt = $this->types->byRewriteSlug($first)) {
            if (count($segments) === 1 && $pt['has_archive']) {
                return $this->finalize($this->postTypeArchive($pt, $paged));
            }
            if (count($segments) > 1) {
                $post = $pt['hierarchical']
                    ? $this->findHierarchical($pt['name'], array_slice($segments, 1))
                    : Post::where('type', $pt['name'])->where('slug', end($segments))->first();
                if ($post) {
                    return $this->finalize($this->singular($post));
                }
            }
        }

        // Archive slug different from rewrite slug (has_archive => 'products').
        foreach ($this->types->all() as $type) {
            if (is_string($type['has_archive']) && $type['has_archive'] === $path) {
                return $this->finalize($this->postTypeArchive($type, $paged));
            }
        }

        // Pages (hierarchical path).
        if ($page = $this->findHierarchical('page', $segments)) {
            return $this->finalize($this->singular($page, $paged));
        }

        // Posts, using the permalink structure.
        if ($post = $this->matchPostStructure($path)) {
            return $this->finalize($this->singular($post));
        }

        // Date archives: /2026, /2026/05, /2026/05/14
        if (preg_match('#^(\d{4})(?:/(\d{1,2}))?(?:/(\d{1,2}))?$#', $path, $d)) {
            return $this->finalize($this->dateArchive((int) $d[1], isset($d[2]) ? (int) $d[2] : null, isset($d[3]) ? (int) $d[3] : null, $paged));
        }

        // Lenient fallback: a bare post slug.
        if (count($segments) === 1 && ($post = Post::where('type', 'post')->where('slug', $first)->first())) {
            return $this->finalize(['redirect' => $post->permalink]);
        }

        return $this->finalize($this->notFound());
    }

    /* ------------------------------------------------------------------ */

    protected function fromQueryString(Request $request, int $paged): ?array
    {
        if ($id = (int) $request->query('page_id')) {
            $page = Post::where('type', 'page')->find($id);

            return $page ? $this->singular($page, $paged) : $this->notFound();
        }
        if ($id = (int) $request->query('p')) {
            $post = Post::find($id);

            return $post ? $this->singular($post) : $this->notFound();
        }
        if ($id = (int) $request->query('cat')) {
            $term = Term::where('taxonomy', 'category')->find($id);

            return $term ? $this->termArchive('category', $term->slug, $paged) : $this->notFound();
        }
        if ($slug = $request->query('tag')) {
            return $this->termArchive('post_tag', (string) $slug, $paged);
        }
        if (($tax = $request->query('taxonomy')) && ($slug = $request->query('term'))) {
            return $this->termArchive((string) $tax, (string) $slug, $paged);
        }
        if ($id = (int) $request->query('author')) {
            $author = User::find($id);

            return $author ? $this->authorArchive($author, $paged) : $this->notFound();
        }
        if (($type = $request->query('post_type')) && ($pt = $this->types->get((string) $type)) && $pt['has_archive']) {
            return $this->postTypeArchive($pt, $paged);
        }

        return null;
    }

    protected function home(int $paged): array
    {
        if (get_option('show_on_front') === 'page' && ($id = (int) get_option('page_on_front'))) {
            $page = Post::where('type', 'page')->find($id);
            if ($page && $page->isPubliclyViewable()) {
                $context = $this->singular($page, $paged);
                array_unshift($context['templates'], 'front-page');
                $context['kind'] = 'front_page';
                $context['is_front_page'] = true;

                return $context;
            }
        }

        $context = $this->listing(
            Post::where('type', 'post'),
            $paged,
            'home',
            ['front-page', 'home', 'index'],
            get_bloginfo('description') ?: get_bloginfo('name')
        );
        $context['is_front_page'] = true;
        $context['is_home'] = true;

        return $context;
    }

    protected function singular(Post $post, int $paged = 1): array
    {
        if (! $post->isPubliclyViewable() && ! (request()->boolean('preview') && current_user_can('edit_post', $post))) {
            return $this->notFound();
        }

        // The page chosen as "Posts page" lists the blog.
        if ($post->type === 'page' && get_option('show_on_front') === 'page' && (int) get_option('page_for_posts') === $post->id) {
            $context = $this->listing(Post::where('type', 'post'), $paged, 'posts_page', ['home', 'index'], $post->title);
            $context['page'] = $post;
            $context['is_home'] = true;

            return $context;
        }

        $templates = [];
        if ($post->type === 'page') {
            if ($post->template) {
                $templates[] = 'template-'.$post->template;
            }
            $templates = array_merge($templates, ["page-{$post->slug}", "page-{$post->id}", 'page']);
        } else {
            if ($post->template) {
                $templates[] = 'template-'.$post->template;
            }
            $templates = array_merge($templates, ["single-{$post->type}-{$post->slug}", "single-{$post->type}", 'single']);
        }
        $templates[] = 'singular';
        $templates[] = 'index';

        return [
            'kind' => $post->type === 'page' ? 'page' : 'single',
            'templates' => $templates,
            'post' => $post,
            'title' => $post->title,
        ];
    }

    protected function search(Request $request, string $query, int $paged): array
    {
        $searchable = array_keys(array_filter($this->types->all(), fn ($t) => ! $t['exclude_from_search']));
        $type = $request->query('post_type');
        $types = $type && in_array($type, $searchable, true) ? [$type] : $searchable;

        $builder = Post::whereIn('type', $types)
            ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")->orWhere('content', 'like', "%{$query}%")->orWhere('excerpt', 'like', "%{$query}%"));

        $context = $this->listing($builder, $paged, 'search', ['search', 'index'], 'Search results for “'.$query.'”');
        $context['search_query'] = $query;

        return $context;
    }

    protected function termArchive(string $taxonomy, string $slug, int $paged): array
    {
        $tax = $this->taxonomies->get($taxonomy);
        $term = Term::where('taxonomy', $taxonomy)->where('slug', $slug)->first();
        if (! $tax || ! $term) {
            return $this->notFound();
        }

        $templates = match ($taxonomy) {
            'category' => ["category-{$term->slug}", "category-{$term->id}", 'category', 'archive', 'index'],
            'post_tag' => ["tag-{$term->slug}", "tag-{$term->id}", 'tag', 'archive', 'index'],
            default => ["taxonomy-{$taxonomy}-{$term->slug}", "taxonomy-{$taxonomy}", 'taxonomy', 'archive', 'index'],
        };

        // Include child terms for hierarchical taxonomies.
        $ids = [$term->id];
        if ($tax['hierarchical']) {
            $queue = [$term->id];
            while ($queue) {
                $children = Term::whereIn('parent_id', $queue)->pluck('id')->all();
                $ids = array_merge($ids, $children);
                $queue = $children;
            }
        }

        $context = $this->listing(
            Post::whereIn('type', $tax['object_types'])->whereHas('terms', fn ($q) => $q->whereIn('terms.id', $ids)),
            $paged,
            'archive',
            $templates,
            $term->name
        );
        $context['archive'] = ['type' => $taxonomy === 'category' ? 'category' : ($taxonomy === 'post_tag' ? 'tag' : 'taxonomy'), 'taxonomy' => $taxonomy, 'term' => $term->toFrontArray(), 'label' => $tax['labels']['singular_name']];

        return $context;
    }

    protected function authorArchive(User $author, int $paged): array
    {
        $context = $this->listing(
            Post::where('type', 'post')->where('author_id', $author->id),
            $paged,
            'archive',
            ["author-{$author->username}", "author-{$author->id}", 'author', 'archive', 'index'],
            $author->name
        );
        $context['archive'] = ['type' => 'author', 'author' => $author->toPublicArray(), 'label' => 'Author'];

        return $context;
    }

    protected function postTypeArchive(array $pt, int $paged): array
    {
        $context = $this->listing(
            Post::where('type', $pt['name']),
            $paged,
            'archive',
            ["archive-{$pt['name']}", 'archive', 'index'],
            $pt['label']
        );
        $context['archive'] = ['type' => 'post_type', 'post_type' => $pt['name'], 'label' => $pt['label'], 'description' => $pt['description']];

        return $context;
    }

    protected function dateArchive(int $year, ?int $month, ?int $day, int $paged): array
    {
        $q = Post::where('type', 'post')->whereYear('published_at', $year);
        $label = (string) $year;
        if ($month) {
            $q->whereMonth('published_at', $month);
            $label = Carbon::create($year, $month, 1)->format('F Y');
        }
        if ($day) {
            $q->whereDay('published_at', $day);
            $label = Carbon::create($year, $month, $day)->format('F j, Y');
        }

        $context = $this->listing($q, $paged, 'archive', ['date', 'archive', 'index'], $label);
        $context['archive'] = ['type' => 'date', 'label' => 'Archives', 'year' => $year, 'month' => $month, 'day' => $day];

        return $context;
    }

    protected function listing($builder, int $paged, string $kind, array $templates, string $title): array
    {
        $perPage = (int) apply_filters('posts_per_page', (int) get_option('posts_per_page', 10), $kind);

        $builder = $builder->visible()->with(['author', 'featuredMedia', 'terms'])
            ->orderByDesc('published_at')->orderByDesc('id');
        $builder = apply_filters('pre_get_posts', $builder, $kind);

        $posts = $builder->paginate($perPage, ['*'], 'paged', $paged);

        if ($paged > 1 && $posts->isEmpty()) {
            return $this->notFound();
        }

        return [
            'kind' => $kind,
            'templates' => $templates,
            'posts' => $posts,
            'title' => $title,
        ];
    }

    protected function findHierarchical(string $type, array $segments): ?Post
    {
        $parentId = null;
        $post = null;
        foreach ($segments as $slug) {
            $post = Post::where('type', $type)->where('slug', $slug)->where('parent_id', $parentId)->first();
            if (! $post) {
                return null;
            }
            $parentId = $post->id;
        }

        return $post;
    }

    protected function matchPostStructure(string $path): ?Post
    {
        if ($this->permalinks->isPlain()) {
            return null;
        }

        [$regex, $tags] = $this->permalinks->structureRegex();
        if (! preg_match($regex, $path, $m)) {
            return null;
        }

        $values = [];
        foreach ($tags as $i => $tag) {
            $values[$tag] = $m[$i + 1] ?? null;
        }

        $q = Post::where('type', 'post');
        if (isset($values['%post_id%'])) {
            $q->whereKey((int) $values['%post_id%']);
        }
        if (isset($values['%postname%'])) {
            $q->where('slug', $values['%postname%']);
        }
        if (isset($values['%year%'])) {
            $q->whereYear('published_at', (int) $values['%year%']);
        }
        if (isset($values['%monthnum%'])) {
            $q->whereMonth('published_at', (int) $values['%monthnum%']);
        }

        return $q->first();
    }

    protected function notFound(): array
    {
        return [
            'kind' => '404',
            'templates' => ['404', 'index'],
            'title' => 'Page not found',
            'status' => 404,
        ];
    }

    protected function finalize(array $context): array
    {
        $context = array_merge([
            'kind' => 'page',
            'templates' => ['index'],
            'post' => null,
            'posts' => null,
            'page' => null,
            'title' => '',
            'archive' => null,
            'search_query' => null,
            'status' => 200,
            'is_front_page' => false,
            'is_home' => false,
            'redirect' => null,
        ], $context);

        $context['templates'] = array_values(array_unique(apply_filters('template_hierarchy', $context['templates'], $context)));

        return apply_filters('cms_request_context', $context);
    }
}
