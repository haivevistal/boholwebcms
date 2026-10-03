<?php

namespace App\Cms\Support;

use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;

/**
 * Builds URLs according to Settings → Permalinks.
 *
 * Structure tags: %year% %monthnum% %day% %hour% %minute% %second% %post_id% %postname% %category% %author%
 * Filters: post_link, page_link, post_type_link, term_link, author_link
 */
class Permalinks
{
    public const STRUCTURES = [
        'plain' => '',
        'day' => '/%year%/%monthnum%/%day%/%postname%',
        'month' => '/%year%/%monthnum%/%postname%',
        'numeric' => '/archives/%post_id%',
        'postname' => '/%postname%',
    ];

    public const TAGS = [
        '%year%' => '(\d{4})',
        '%monthnum%' => '(\d{1,2})',
        '%day%' => '(\d{1,2})',
        '%hour%' => '(\d{1,2})',
        '%minute%' => '(\d{1,2})',
        '%second%' => '(\d{1,2})',
        '%post_id%' => '(\d+)',
        '%postname%' => '([^/]+)',
        '%category%' => '(.+?)',
        '%author%' => '([^/]+)',
    ];

    public function structure(): string
    {
        return rtrim((string) get_option('permalink_structure', '/%postname%'), '/');
    }

    public function isPlain(): bool
    {
        return $this->structure() === '';
    }

    public function categoryBase(): string
    {
        return trim((string) get_option('category_base', '') ?: 'category', '/');
    }

    public function tagBase(): string
    {
        return trim((string) get_option('tag_base', '') ?: 'tag', '/');
    }

    public function home(string $path = ''): string
    {
        $base = rtrim(url('/'), '/');

        return $path === '' ? $base.'/' : $base.'/'.ltrim($path, '/');
    }

    public function post(Post $post): string
    {
        // A static front page links to the site root.
        if ($post->type === 'page' && get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $post->id) {
            return apply_filters('page_link', $this->home(), $post);
        }

        if ($post->type === 'page') {
            $link = $this->isPlain() ? $this->home('?page_id='.$post->id) : $this->home($this->hierarchicalPath($post));

            return apply_filters('page_link', $link, $post);
        }

        if ($post->type === 'post') {
            $link = $this->isPlain() ? $this->home('?p='.$post->id) : $this->home(ltrim($this->fillStructure($post), '/'));

            return apply_filters('post_link', $link, $post);
        }

        $type = app(PostTypeRegistry::class)->get($post->type);
        if ($this->isPlain() || ! $type || $type['rewrite'] === false) {
            $link = $this->home('?post_type='.$post->type.'&p='.$post->id);
        } else {
            $path = $type['hierarchical'] ? $this->hierarchicalPath($post) : $post->slug;
            $link = $this->home($type['rewrite']['slug'].'/'.$path);
        }

        return apply_filters('post_type_link', $link, $post);
    }

    public function previewUrl(Post $post): string
    {
        return $this->home('?preview=1&p='.$post->id);
    }

    public function term(Term $term): string
    {
        if ($term->taxonomy === 'category') {
            $link = $this->isPlain() ? $this->home('?cat='.$term->id) : $this->home($this->categoryBase().'/'.$this->termPath($term));
        } elseif ($term->taxonomy === 'post_tag') {
            $link = $this->isPlain() ? $this->home('?tag='.$term->slug) : $this->home($this->tagBase().'/'.$term->slug);
        } else {
            $tax = app(TaxonomyRegistry::class)->get($term->taxonomy);
            $link = ($this->isPlain() || ! $tax || $tax['rewrite'] === false)
                ? $this->home('?taxonomy='.$term->taxonomy.'&term='.$term->slug)
                : $this->home($tax['rewrite']['slug'].'/'.$this->termPath($term));
        }

        return apply_filters('term_link', $link, $term);
    }

    public function author(User $user): string
    {
        $link = $this->isPlain() ? $this->home('?author='.$user->id) : $this->home('author/'.$user->username);

        return apply_filters('author_link', $link, $user);
    }

    public function archive(string $postType): ?string
    {
        if ($postType === 'post') {
            return $this->postsPage();
        }

        $type = app(PostTypeRegistry::class)->get($postType);
        if (! $type || ! $type['has_archive']) {
            return null;
        }

        $slug = is_string($type['has_archive']) ? $type['has_archive'] : ($type['rewrite']['slug'] ?? $postType);

        return $this->isPlain() ? $this->home('?post_type='.$postType) : $this->home($slug);
    }

    public function postsPage(): string
    {
        if (get_option('show_on_front') === 'page' && ($id = (int) get_option('page_for_posts'))) {
            $page = Post::find($id);
            if ($page) {
                return $this->post($page);
            }
        }

        return $this->home();
    }

    public function search(string $query = ''): string
    {
        return $this->home('?s='.urlencode($query));
    }

    public function paged(string $base, int $page): string
    {
        if ($page <= 1) {
            return $base;
        }

        $parts = parse_url($base);
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $path = rtrim(strtok($base, '?'), '/');

        if ($this->isPlain() || $query !== '') {
            return $path.($query ? $query.'&' : '/?').'paged='.$page;
        }

        return $path.'/page/'.$page;
    }

    public function hierarchicalPath(Post $post): string
    {
        $slugs = [$post->slug];
        $parent = $post->parent_id ? Post::find($post->parent_id) : null;
        $guard = 0;
        while ($parent && $guard++ < 20) {
            array_unshift($slugs, $parent->slug);
            $parent = $parent->parent_id ? Post::find($parent->parent_id) : null;
        }

        return implode('/', $slugs);
    }

    public function termPath(Term $term): string
    {
        $slugs = [$term->slug];
        $parent = $term->parent_id ? Term::find($term->parent_id) : null;
        $guard = 0;
        while ($parent && $guard++ < 20) {
            array_unshift($slugs, $parent->slug);
            $parent = $parent->parent_id ? Term::find($parent->parent_id) : null;
        }

        return implode('/', $slugs);
    }

    public function fillStructure(Post $post): string
    {
        $date = $post->published_at ?? $post->created_at ?? now();
        $category = $post->terms->firstWhere('taxonomy', 'category');

        return strtr($this->structure(), [
            '%year%' => $date->format('Y'),
            '%monthnum%' => $date->format('m'),
            '%day%' => $date->format('d'),
            '%hour%' => $date->format('H'),
            '%minute%' => $date->format('i'),
            '%second%' => $date->format('s'),
            '%post_id%' => (string) $post->id,
            '%postname%' => $post->slug,
            '%category%' => $category ? $this->termPath($category) : 'uncategorized',
            '%author%' => $post->author?->username ?? 'admin',
        ]);
    }

    /**
     * Turn the post structure into a regex. Returns [regex, tag order].
     */
    public function structureRegex(): array
    {
        $structure = trim($this->structure(), '/');
        preg_match_all('/%[a-z_]+%/', $structure, $m);
        $tags = $m[0];

        $regex = preg_quote($structure, '#');
        foreach (self::TAGS as $tag => $pattern) {
            $regex = str_replace(preg_quote($tag, '#'), $pattern, $regex);
        }

        return ['#^'.$regex.'$#', $tags];
    }
}
