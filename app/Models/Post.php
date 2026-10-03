<?php

namespace App\Models;

use App\Cms\Support\Permalinks;
use App\Models\Concerns\HasMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Posts, pages and every custom post type share this table (like wp_posts).
 */
class Post extends Model
{
    use HasMeta;

    public const STATUSES = [
        'publish' => 'Published',
        'future' => 'Scheduled',
        'draft' => 'Draft',
        'pending' => 'Pending Review',
        'private' => 'Private',
        'trash' => 'Trash',
    ];

    protected $fillable = [
        'type', 'status', 'title', 'slug', 'content', 'excerpt', 'author_id', 'parent_id', 'menu_order',
        'template', 'comment_status', 'featured_media_id', 'password', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'menu_order' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (! $post->slug) {
                $post->slug = Str::slug($post->title) ?: (string) Str::uuid();
            }
            $post->slug = static::uniqueSlug($post);

            if ($post->status === 'publish' && ! $post->published_at) {
                $post->published_at = now();
            }
            if ($post->status === 'publish' && $post->published_at && $post->published_at->isFuture()) {
                $post->status = 'future';
            }
        });
    }

    /* ---------------------------------------------------------------- */

    public function meta(): HasMany
    {
        return $this->hasMany(PostMeta::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_relationships');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /* ---------------------------------------------------------------- */

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'publish')->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * Visible on the front end for the current visitor.
     */
    public function scopeVisible(Builder $q): Builder
    {
        if (current_user_can('read_private_posts')) {
            return $q->whereIn('status', ['publish', 'private'])->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
        }

        return $q->published();
    }

    public function getPermalinkAttribute(): string
    {
        return app(Permalinks::class)->post($this);
    }

    public function isPubliclyViewable(): bool
    {
        if ($this->status === 'publish' && (! $this->published_at || ! $this->published_at->isFuture())) {
            return true;
        }

        return $this->status === 'private' && current_user_can('read_post', $this);
    }

    /* ---------------------------------------------------------------- */

    public function termsOf(string $taxonomy)
    {
        return $this->terms->where('taxonomy', $taxonomy)->values();
    }

    /**
     * Replace this post's terms for one taxonomy and refresh term counts.
     *
     * @param  array<int, int|string>  $terms  term ids, or names (created when missing)
     * @param  bool  $byName  treat every value as a name (used for tags, where "2024" is a name)
     */
    public function setTerms(string $taxonomy, array $terms, bool $byName = false): void
    {
        $ids = [];
        foreach ($terms as $term) {
            if (! $byName && is_numeric($term)) {
                $ids[] = (int) $term;
            } elseif (is_string($term) && trim($term) !== '') {
                $ids[] = Term::firstOrCreate(
                    ['taxonomy' => $taxonomy, 'slug' => Str::slug($term)],
                    ['name' => trim($term)]
                )->id;
            }
        }

        $current = $this->terms()->where('taxonomy', $taxonomy)->pluck('terms.id')->all();
        $this->terms()->detach(array_diff($current, $ids));
        $this->terms()->syncWithoutDetaching($ids);
        $this->unsetRelation('terms');

        Term::recount(array_unique(array_merge($current, $ids)));
    }

    public function excerptText(int $words = 55): string
    {
        $excerpt = $this->excerpt ?: cms_trim_words((string) $this->content, $words, '…');

        return apply_filters('get_the_excerpt', $excerpt, $this);
    }

    /**
     * Data passed to themes. Content is run through "the_content" (shortcodes
     * are executed here, on the server).
     */
    public function toFrontArray(bool $withContent = true): array
    {
        $this->loadMissing(['author', 'featuredMedia', 'terms']);

        $protected = $this->password && ! session()->get('post_password_'.$this->id);

        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'slug' => $this->slug,
            'title' => apply_filters('the_title', $this->title, $this),
            'excerpt' => $protected ? '' : $this->excerptText(),
            'content' => $withContent
                ? ($protected ? null : apply_filters('the_content', (string) $this->content, $this))
                : null,
            'password_required' => $protected,
            'permalink' => $this->permalink,
            'date' => $this->published_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'date_formatted' => format_cms_date($this->published_at ?? $this->created_at),
            'modified' => $this->updated_at?->toIso8601String(),
            'author' => $this->author?->toPublicArray(),
            'featured_image' => $this->featuredMedia?->toFrontArray(),
            'template' => $this->template,
            'parent_id' => $this->parent_id,
            'menu_order' => $this->menu_order,
            'comment_status' => $this->comment_status,
            'comment_count' => $this->comment_count,
            'terms' => $this->terms->groupBy('taxonomy')->map(fn ($terms) => $terms->map(fn (Term $t) => $t->toFrontArray())->values())->all(),
            'meta' => (object) apply_filters('post_public_meta', [], $this),
        ];

        return apply_filters('cms_post_data', $data, $this);
    }

    /* ---------------------------------------------------------------- */

    public static function uniqueSlug(Post $post): string
    {
        $base = Str::slug($post->slug) ?: 'post';
        $slug = $base;
        $i = 2;

        $reserved = apply_filters('reserved_slugs', ['admin', 'login', 'logout', 'register', 'content', 'storage', 'cms-ajax', 'page', 'feed', 'author', 'search']);

        while (
            (in_array($slug, $reserved, true) && $post->type === 'page' && ! $post->parent_id)
            || static::where('type', $post->type)
                ->where('slug', $slug)
                ->where('parent_id', $post->parent_id)
                ->when($post->exists, fn ($q) => $q->where('id', '!=', $post->id))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Query builder from WordPress-like arguments (used by get_posts()).
     */
    public static function queryFromArgs(array $args): Builder
    {
        $args = array_merge([
            'type' => 'post', 'status' => 'publish', 'limit' => 10, 'offset' => 0,
            'orderby' => 'published_at', 'order' => 'desc',
        ], $args);

        $q = static::query()->with(['author', 'featuredMedia', 'terms']);

        $q->whereIn('type', (array) $args['type']);
        if ($args['status'] !== 'any') {
            $q->whereIn('status', (array) $args['status']);
        }
        if ($args['status'] === 'publish') {
            $q->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
        }
        if (! empty($args['include'])) {
            $q->whereIn('id', (array) $args['include']);
        }
        if (! empty($args['exclude'])) {
            $q->whereNotIn('id', (array) $args['exclude']);
        }
        if (! empty($args['author'])) {
            $q->where('author_id', $args['author']);
        }
        if (array_key_exists('parent', $args)) {
            $q->where('parent_id', $args['parent'] ?: null);
        }
        if (! empty($args['search'])) {
            $s = '%'.$args['search'].'%';
            $q->where(fn ($w) => $w->where('title', 'like', $s)->orWhere('content', 'like', $s)->orWhere('excerpt', 'like', $s));
        }
        if (! empty($args['meta_key'])) {
            $q->whereHas('meta', function ($m) use ($args) {
                $m->where('meta_key', $args['meta_key']);
                if (array_key_exists('meta_value', $args)) {
                    $m->where('meta_value', $args['meta_compare'] ?? '=', $args['meta_value']);
                }
            });
        }
        if (! empty($args['term'])) {
            $terms = (array) $args['term'];
            $q->whereHas('terms', function ($t) use ($terms, $args) {
                if (! empty($args['taxonomy'])) {
                    $t->where('taxonomy', $args['taxonomy']);
                }
                $t->where(fn ($w) => $w->whereIn('terms.id', array_filter($terms, 'is_numeric'))->orWhereIn('terms.slug', $terms));
            });
        }

        $orderby = in_array($args['orderby'], ['published_at', 'created_at', 'updated_at', 'title', 'menu_order', 'id', 'comment_count'], true) ? $args['orderby'] : 'published_at';
        $q->orderBy($orderby, strtolower($args['order']) === 'asc' ? 'asc' : 'desc');

        if ((int) $args['limit'] > 0) {
            $q->limit((int) $args['limit'])->offset((int) $args['offset']);
        }

        return apply_filters('posts_query', $q, $args);
    }

    /**
     * Create/update a post from a plain array (cms_insert_post()).
     */
    public static function insertFromArray(array $data): Post
    {
        $post = ! empty($data['id']) ? static::findOrFail($data['id']) : new static(['type' => 'post', 'status' => 'draft']);
        $update = $post->exists;

        $data = apply_filters('cms_insert_post_data', $data, $post);

        $post->fill(array_intersect_key($data, array_flip($post->getFillable())));
        if (! $post->author_id) {
            $post->author_id = current_user_id() ?: User::orderBy('id')->value('id');
        }
        $post->save();

        foreach ($data['terms'] ?? [] as $taxonomy => $terms) {
            $post->setTerms($taxonomy, (array) $terms);
        }
        foreach ($data['meta'] ?? [] as $key => $value) {
            $post->setMeta($key, $value);
        }

        do_action("save_post_{$post->type}", $post, $update);
        do_action('save_post', $post, $update);

        return $post;
    }
}
