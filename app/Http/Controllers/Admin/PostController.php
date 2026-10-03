<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Admin\MetaBoxRegistry;
use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Cms\Extensions\ThemeManager;
use App\Cms\Support\Permalinks;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * One controller for every post type: posts, pages and custom types
 * registered by plugins with register_post_type().
 */
class PostController extends Controller
{
    public function __construct(
        protected PostTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
    ) {}

    public function index(Request $request, string $type)
    {
        $pt = $this->type($type);
        $this->authorizeCap($pt['capabilities']['edit']);

        $status = $request->query('status', 'all');
        $query = Post::query()->with(['author:id,name,username', 'terms', 'featuredMedia'])->where('type', $type);

        if ($status === 'all') {
            $query->where('status', '!=', 'trash');
        } elseif ($status === 'mine') {
            $query->where('author_id', current_user_id())->where('status', '!=', 'trash');
        } else {
            $query->where('status', $status);
        }

        // Without edit_others, users only see their own drafts/pending.
        if (! current_user_can($pt['capabilities']['edit_others'])) {
            $query->where(fn ($q) => $q->where('author_id', current_user_id())->orWhere('status', 'publish'));
        }

        if ($s = trim((string) $request->query('s'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('content', 'like', "%{$s}%"));
        }
        if ($term = $request->query('term')) {
            $query->whereHas('terms', fn ($q) => $q->where('terms.id', $term));
        }
        if ($month = $request->query('m')) {
            [$y, $mo] = array_pad(explode('-', $month), 2, null);
            if ($y && $mo) {
                $query->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $mo);
            }
        }
        if ($author = $request->query('author')) {
            $query->where('author_id', $author);
        }

        $sortable = ['title', 'published_at', 'created_at', 'updated_at', 'menu_order', 'comment_count'];
        $orderby = in_array($request->query('orderby'), $sortable, true) ? $request->query('orderby') : ($pt['hierarchical'] ? 'menu_order' : 'created_at');
        $order = $request->query('order') === 'asc' ? 'asc' : ($pt['hierarchical'] && ! $request->query('orderby') ? 'asc' : 'desc');
        $query->orderBy($orderby, $order)->orderBy('id', 'desc');

        $query = apply_filters('admin_posts_query', $query, $type, $request);

        $perPage = (int) apply_filters('edit_posts_per_page', 20, $type);
        $posts = $query->paginate($perPage)->withQueryString();

        $columns = apply_filters("manage_{$type}_posts_columns", $this->defaultColumns($pt));

        $posts->through(function (Post $post) use ($type, $pt, $columns) {
            $row = [
                'id' => $post->id,
                'title' => $post->title,
                'status' => $post->status,
                'slug' => $post->slug,
                'author' => $post->author?->name,
                'author_id' => $post->author_id,
                'date' => format_cms_date($post->published_at ?? $post->created_at, 'Y/m/d \a\t g:i a'),
                'modified' => format_cms_date($post->updated_at, 'Y/m/d \a\t g:i a'),
                'comment_count' => $post->comment_count,
                'parent_id' => $post->parent_id,
                'menu_order' => $post->menu_order,
                'permalink' => $post->permalink,
                'preview_url' => app(Permalinks::class)->previewUrl($post),
                'thumbnail' => $post->featuredMedia?->sizeUrl('thumbnail'),
                'terms' => $post->terms->groupBy('taxonomy')->map(fn ($t) => $t->map(fn ($x) => ['id' => $x->id, 'name' => $x->name]))->all(),
                'can_edit' => current_user_can('edit_post', $post),
                'can_delete' => current_user_can('delete_post', $post),
                'is_front_page' => $type === 'page' && (int) get_option('page_on_front') === $post->id && get_option('show_on_front') === 'page',
                'is_posts_page' => $type === 'page' && (int) get_option('page_for_posts') === $post->id && get_option('show_on_front') === 'page',
                'is_privacy_page' => $type === 'page' && (int) get_option('privacy_policy_page') === $post->id,
                'custom' => [],
            ];

            // Custom column values (HTML) from plugins.
            foreach (array_keys($columns) as $column) {
                if (! in_array($column, ['cb', 'title', 'author', 'date', 'comments', 'thumbnail'], true) && ! str_starts_with($column, 'taxonomy-')) {
                    $row['custom'][$column] = (string) apply_filters("manage_{$type}_posts_custom_column", '', $column, $post);
                }
            }

            $row['actions'] = apply_filters('post_row_actions', [], $post);

            return $row;
        });

        $counts = Post::where('type', $type)
            ->when(! current_user_can($pt['capabilities']['edit_others']), fn ($q) => $q->where(fn ($w) => $w->where('author_id', current_user_id())->orWhere('status', 'publish')))
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $statusTabs = [['key' => 'all', 'label' => 'All', 'count' => $counts->except('trash')->sum()]];
        $statusTabs[] = ['key' => 'mine', 'label' => 'Mine', 'count' => Post::where('type', $type)->where('author_id', current_user_id())->where('status', '!=', 'trash')->count()];
        foreach (Post::STATUSES as $key => $label) {
            if (($counts[$key] ?? 0) > 0) {
                $statusTabs[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key]];
            }
        }

        $months = Post::where('type', $type)->where('status', '!=', 'trash')
            ->orderByDesc('created_at')->pluck('created_at')
            ->map(fn ($d) => $d?->format('Y-m'))->filter()->unique()->values()
            ->map(fn ($m) => ['value' => $m, 'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('F Y')]);

        $filterTaxonomy = collect($this->taxonomies->forType($type))->first(fn ($t) => $t['hierarchical']);

        return Inertia::render('Posts/Index', [
            'postType' => $this->types->forClient($type),
            'posts' => $posts,
            'columns' => $columns,
            'statusTabs' => $statusTabs,
            'filters' => $request->only(['status', 's', 'term', 'm', 'author', 'orderby', 'order']) + ['status' => $status],
            'months' => $months,
            'filterTerms' => $filterTaxonomy ? Term::where('taxonomy', $filterTaxonomy['name'])->orderBy('name')->get(['id', 'name']) : [],
            'filterTaxonomy' => $filterTaxonomy ? $this->taxonomies->forClient($filterTaxonomy['name']) : null,
            'bulkActions' => apply_filters("bulk_actions-{$type}", $status === 'trash'
                ? ['restore' => 'Restore', 'delete' => 'Delete permanently']
                : ['publish' => 'Publish', 'draft' => 'Move to Draft', 'trash' => 'Move to Trash']),
            'can' => [
                'create' => current_user_can($pt['capabilities']['edit']),
                'delete_others' => current_user_can($pt['capabilities']['delete_others']),
            ],
        ]);
    }

    public function create(string $type)
    {
        $pt = $this->type($type);
        $this->authorizeCap($pt['capabilities']['edit']);

        $post = new Post([
            'type' => $type,
            'status' => 'draft',
            'comment_status' => ($type === 'post' ? get_option('default_comment_status', true) : false) ? 'open' : 'closed',
            'author_id' => current_user_id(),
        ]);

        do_action('load-post-new', $type);

        return Inertia::render('Posts/Edit', $this->editorProps($pt, $post));
    }

    public function store(Request $request, string $type)
    {
        $pt = $this->type($type);
        $this->authorizeCap($pt['capabilities']['edit']);

        $post = new Post(['type' => $type, 'author_id' => current_user_id()]);
        $post = $this->save($request, $pt, $post);

        return redirect()->route('admin.content.edit', [$type, $post->id])
            ->with('success', $this->savedMessage($pt, $post, true));
    }

    public function edit(string $type, Post $post)
    {
        $pt = $this->type($type);
        abort_unless($post->type === $type, 404);
        $this->authorizeCap('edit_post', $post);

        do_action('load-post', $post);

        return Inertia::render('Posts/Edit', $this->editorProps($pt, $post));
    }

    public function update(Request $request, string $type, Post $post)
    {
        $pt = $this->type($type);
        abort_unless($post->type === $type, 404);
        $this->authorizeCap('edit_post', $post);

        $post = $this->save($request, $pt, $post);

        return redirect()->route('admin.content.edit', [$type, $post->id])
            ->with('success', $this->savedMessage($pt, $post, false));
    }

    public function trash(string $type, Post $post)
    {
        $this->authorizeCap('delete_post', $post);
        $this->moveToTrash($post);

        return back()->with('success', '1 item moved to the Trash.');
    }

    public function restore(string $type, Post $post)
    {
        $this->authorizeCap('delete_post', $post);
        $this->restoreFromTrash($post);

        return back()->with('success', '1 item restored from the Trash.');
    }

    public function destroy(string $type, Post $post)
    {
        $this->authorizeCap('delete_post', $post);
        $this->deletePermanently($post);

        return redirect()->route('admin.content.index', [$type, 'status' => 'trash'])->with('success', '1 item permanently deleted.');
    }

    public function duplicate(string $type, Post $post)
    {
        $pt = $this->type($type);
        $this->authorizeCap($pt['capabilities']['edit']);

        $copy = $post->replicate(['comment_count', 'published_at']);
        $copy->title = trim($post->title.' (Copy)');
        $copy->slug = $post->slug.'-copy';
        $copy->status = 'draft';
        $copy->author_id = current_user_id();
        $copy->save();
        $copy->terms()->sync($post->terms->pluck('id'));
        foreach ($post->allMeta() as $key => $value) {
            $copy->setMeta($key, $value);
        }

        do_action('duplicate_post', $copy, $post);

        return redirect()->route('admin.content.edit', [$type, $copy->id])->with('success', 'Duplicated as a new draft.');
    }

    public function emptyTrash(string $type)
    {
        $pt = $this->type($type);
        $this->authorizeCap($pt['capabilities']['delete_others']);

        $count = 0;
        Post::where('type', $type)->where('status', 'trash')->get()->each(function ($post) use (&$count) {
            $this->deletePermanently($post);
            $count++;
        });

        return back()->with('success', "{$count} item(s) permanently deleted.");
    }

    public function bulk(Request $request, string $type)
    {
        $pt = $this->type($type);
        $data = $request->validate(['action' => 'required|string', 'ids' => 'required|array', 'ids.*' => 'integer']);
        $posts = Post::where('type', $type)->whereIn('id', $data['ids'])->get();
        $done = 0;

        foreach ($posts as $post) {
            switch ($data['action']) {
                case 'trash':
                    if (current_user_can('delete_post', $post)) {
                        $this->moveToTrash($post);
                        $done++;
                    }
                    break;
                case 'restore':
                    if (current_user_can('delete_post', $post)) {
                        $this->restoreFromTrash($post);
                        $done++;
                    }
                    break;
                case 'delete':
                    if (current_user_can('delete_post', $post)) {
                        $this->deletePermanently($post);
                        $done++;
                    }
                    break;
                case 'publish':
                case 'draft':
                    if (current_user_can('edit_post', $post) && ($data['action'] === 'draft' || current_user_can($pt['capabilities']['publish']))) {
                        $old = $post->status;
                        $post->status = $data['action'];
                        $post->save();
                        do_action('transition_post_status', $post->status, $old, $post);
                        $done++;
                    }
                    break;
            }
        }

        // Custom bulk actions registered with the "bulk_actions-{type}" filter.
        $redirect = apply_filters("handle_bulk_actions-{$type}", null, $data['action'], $data['ids']);
        if (is_string($redirect)) {
            return redirect($redirect);
        }

        return back()->with('success', $done ? "{$done} item(s) updated." : 'Nothing was changed.');
    }

    /* ------------------------------------------------------------------ */

    protected function save(Request $request, array $pt, Post $post): Post
    {
        $type = $pt['name'];
        $statuses = array_keys(Post::STATUSES);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:191',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'status' => ['required', Rule::in($statuses)],
            'published_at' => 'nullable|date',
            'parent_id' => 'nullable|integer|exists:posts,id',
            'menu_order' => 'nullable|integer',
            'template' => 'nullable|string|max:191',
            'comment_status' => 'nullable|in:open,closed',
            'featured_media_id' => 'nullable|integer|exists:media,id',
            'password' => 'nullable|string|max:191',
            'author_id' => 'nullable|integer|exists:users,id',
            'terms' => 'nullable|array',
            'meta' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.key' => 'required_with:custom_fields|string|max:191',
            'custom_fields.*.value' => 'nullable',
            'meta_box' => 'nullable|array',
        ]);

        $isNew = ! $post->exists;
        $oldStatus = $post->status ?? 'new';

        // Users without publish capability submit for review instead.
        if (in_array($data['status'], ['publish', 'future', 'private'], true) && ! current_user_can($pt['capabilities']['publish'])) {
            $data['status'] = 'pending';
        }

        if (! current_user_can('unfiltered_html') && isset($data['content'])) {
            $data['content'] = kses_post($data['content']);
        }

        if (isset($data['author_id']) && (int) $data['author_id'] !== (int) $post->author_id && ! current_user_can($pt['capabilities']['edit_others'])) {
            unset($data['author_id']);
        }

        if (! empty($data['parent_id']) && $post->exists && (int) $data['parent_id'] === $post->id) {
            $data['parent_id'] = null;
        }

        $data = apply_filters('cms_insert_post_data', $data, $post, $request);

        $post->fill([
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? $post->slug ?? '',
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? null,
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?? $post->published_at,
            'parent_id' => $pt['hierarchical'] ? ($data['parent_id'] ?? null) : null,
            'menu_order' => $data['menu_order'] ?? 0,
            'template' => $data['template'] ?? null,
            'comment_status' => $data['comment_status'] ?? 'closed',
            'featured_media_id' => $data['featured_media_id'] ?? null,
            'password' => $data['password'] ?? null,
        ]);
        if (! empty($data['author_id'])) {
            $post->author_id = $data['author_id'];
        }
        if (! $post->slug && ! $post->title) {
            $post->slug = 'draft-'.now()->format('YmdHis');
        }

        if ($post->status === 'publish' && $request->has('published_at') && empty($data['published_at']) && $oldStatus !== 'publish') {
            $post->published_at = now();
        }

        DB::transaction(function () use ($post, $data, $pt, $type) {
            $post->save();

            foreach ($this->taxonomies->forType($type) as $taxName => $tax) {
                if (array_key_exists($taxName, $data['terms'] ?? [])) {
                    $post->setTerms($taxName, array_values((array) $data['terms'][$taxName]), ! $tax['hierarchical']);
                }
            }

            // Default category for posts.
            if ($type === 'post' && $post->terms()->where('taxonomy', 'category')->doesntExist()) {
                $default = (int) get_option('default_category', 0);
                if ($default && Term::whereKey($default)->exists()) {
                    $post->setTerms('category', [$default]);
                }
            }

            app(MetaBoxRegistry::class)->save($post, $data['meta'] ?? []);

            if (in_array('custom-fields', $pt['supports'], true) && isset($data['custom_fields'])) {
                $keep = [];
                foreach ($data['custom_fields'] as $field) {
                    $key = trim($field['key']);
                    if ($key === '' || str_starts_with($key, '_')) {
                        continue;
                    }
                    $post->setMeta($key, $field['value']);
                    $keep[] = $key;
                }
                $post->meta()->where('meta_key', 'not like', '\_%')->whereNotIn('meta_key', $this->metaBoxKeys($type))->whereNotIn('meta_key', $keep)->delete();
                $post->flushMetaCache();
            }
        });

        $post->refresh();

        if ($oldStatus !== $post->status) {
            do_action('transition_post_status', $post->status, $oldStatus, $post);
            do_action("{$post->status}_{$type}", $post);
        }

        // HTML meta boxes post their inputs as meta_box[...]; plugins read them here.
        do_action("save_post_{$type}", $post, ! $isNew, $request);
        do_action('save_post', $post, ! $isNew, $request);

        return $post;
    }

    protected function editorProps(array $pt, Post $post): array
    {
        $type = $pt['name'];
        $post->loadMissing(['terms', 'featuredMedia']);

        $taxonomies = [];
        foreach ($this->taxonomies->forType($type) as $name => $tax) {
            if (! $tax['show_ui']) {
                continue;
            }
            $taxonomies[] = [
                ...$this->taxonomies->forClient($name),
                'terms' => $tax['hierarchical'] ? Term::where('taxonomy', $name)->orderBy('name')->get(['id', 'name', 'parent_id']) : [],
                'selected' => $post->terms->where('taxonomy', $name)->map(fn ($t) => $tax['hierarchical'] ? $t->id : $t->name)->values(),
                'can_manage' => current_user_can($tax['capability']),
            ];
        }

        $customFields = [];
        if (in_array('custom-fields', $pt['supports'], true) && $post->exists) {
            $boxKeys = $this->metaBoxKeys($type);
            foreach ($post->allMeta() as $k => $v) {
                if (! str_starts_with($k, '_') && ! in_array($k, $boxKeys, true)) {
                    $customFields[] = ['key' => $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)];
                }
            }
        }

        $props = [
            'postType' => $this->types->forClient($type),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'excerpt' => $post->excerpt,
                'status' => $post->status ?: 'draft',
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'author_id' => $post->author_id,
                'parent_id' => $post->parent_id,
                'menu_order' => $post->menu_order ?? 0,
                'template' => $post->template,
                'comment_status' => $post->comment_status,
                'password' => $post->password,
                'featured_media' => $post->featuredMedia ? ['id' => $post->featuredMedia->id, 'url' => $post->featuredMedia->sizeUrl('medium'), 'alt' => $post->featuredMedia->alt] : null,
                'permalink' => $post->exists ? $post->permalink : null,
                'preview_url' => $post->exists ? app(Permalinks::class)->previewUrl($post) : null,
                'updated_at' => $post->updated_at?->toIso8601String(),
            ],
            'permalinkBase' => $this->permalinkBase($pt, $post),
            'taxonomies' => $taxonomies,
            'metaBoxes' => app(MetaBoxRegistry::class)->resolveFor($type, $post->exists ? $post : null),
            'customFields' => $customFields,
            'pageTemplates' => in_array('page-attributes', $pt['supports'], true) ? app(ThemeManager::class)->pageTemplates($type) : [],
            'parents' => $pt['hierarchical']
                ? Post::where('type', $type)->where('status', '!=', 'trash')->when($post->exists, fn ($q) => $q->where('id', '!=', $post->id))
                    ->orderBy('menu_order')->orderBy('title')->get(['id', 'title', 'parent_id'])
                : [],
            'authors' => current_user_can($pt['capabilities']['edit_others'])
                ? User::whereIn('role', $this->authorRoles())->orderBy('name')->get(['id', 'name'])
                : [],
            'statuses' => Post::STATUSES,
            'editorMode' => get_option('default_editor_mode', 'visual'),
            'shortcodes' => app(\App\Cms\Shortcodes\ShortcodeManager::class)->tags(),
            'can' => [
                'publish' => current_user_can($pt['capabilities']['publish']),
                'delete' => $post->exists && current_user_can('delete_post', $post),
                'unfiltered_html' => current_user_can('unfiltered_html'),
                'upload_files' => current_user_can('upload_files'),
            ],
        ];

        return apply_filters('admin_post_editor_props', $props, $post, $type);
    }

    protected function permalinkBase(array $pt, Post $post): ?string
    {
        if (! $pt['public']) {
            return null;
        }
        $permalinks = app(Permalinks::class);
        if ($permalinks->isPlain()) {
            return null;
        }
        if ($pt['name'] === 'post') {
            $sample = $post->exists ? $post : new Post(['slug' => '%postname%', 'type' => 'post', 'published_at' => now()]);
            $structure = $permalinks->home(ltrim(str_replace($sample->slug, '%postname%', $permalinks->fillStructure($sample)), '/'));

            return $structure;
        }
        if ($pt['name'] === 'page') {
            $parent = $post->parent_id ? Post::find($post->parent_id) : null;

            return $permalinks->home(($parent ? $permalinks->hierarchicalPath($parent).'/' : '').'%postname%');
        }

        return $permalinks->home(($pt['rewrite']['slug'] ?? $pt['name']).'/%postname%');
    }

    protected function metaBoxKeys(string $type): array
    {
        $keys = [];
        foreach (app(MetaBoxRegistry::class)->forType($type) as $box) {
            foreach ($box['fields'] as $f) {
                if (! empty($f['name'])) {
                    $keys[] = $f['name'];
                }
            }
        }

        return $keys;
    }

    protected function authorRoles(): array
    {
        $roles = [];
        foreach (app(\App\Cms\Support\Capabilities::class)->roles() as $slug => $caps) {
            if (in_array('*', $caps, true) || in_array('edit_posts', $caps, true)) {
                $roles[] = $slug;
            }
        }

        return $roles;
    }

    protected function defaultColumns(array $pt): array
    {
        $columns = ['cb' => '', 'title' => 'Title'];
        if (in_array('thumbnail', $pt['supports'], true) && ! in_array($pt['name'], ['post', 'page'], true)) {
            $columns = ['cb' => '', 'thumbnail' => 'Image', 'title' => 'Title'];
        }
        if (in_array('author', $pt['supports'], true)) {
            $columns['author'] = 'Author';
        }
        foreach ($this->taxonomies->forType($pt['name']) as $name => $tax) {
            if ($tax['show_admin_column']) {
                $columns['taxonomy-'.$name] = $tax['label'];
            }
        }
        if (in_array('comments', $pt['supports'], true)) {
            $columns['comments'] = 'Comments';
        }
        $columns['date'] = 'Date';

        return $columns;
    }

    protected function moveToTrash(Post $post): void
    {
        do_action('wp_trash_post', $post);
        $post->setMeta('_trash_meta_status', $post->status);
        $old = $post->status;
        $post->status = 'trash';
        $post->save();
        do_action('trashed_post', $post);
        do_action('transition_post_status', 'trash', $old, $post);
    }

    protected function restoreFromTrash(Post $post): void
    {
        $status = $post->getMeta('_trash_meta_status', 'draft');
        $post->status = $status === 'trash' ? 'draft' : $status;
        $post->save();
        $post->deleteMeta('_trash_meta_status');
        do_action('untrashed_post', $post);
    }

    protected function deletePermanently(Post $post): void
    {
        do_action('before_delete_post', $post);
        $termIds = $post->terms()->pluck('terms.id')->all();
        Post::where('parent_id', $post->id)->update(['parent_id' => $post->parent_id]);
        $post->delete();
        Term::recount($termIds);
        do_action('deleted_post', $post->id, $post);
    }

    protected function savedMessage(array $pt, Post $post, bool $created): string
    {
        $singular = $pt['labels']['singular_name'];

        return match ($post->status) {
            'publish' => $created ? "{$singular} published." : "{$singular} updated.",
            'future' => "{$singular} scheduled for ".format_cms_date($post->published_at, 'M j, Y g:i a').'.',
            'pending' => "{$singular} submitted for review.",
            'private' => "{$singular} saved privately.",
            default => "{$singular} draft saved.",
        };
    }

    protected function type(string $type): array
    {
        $pt = $this->types->get($type);
        abort_unless($pt && $pt['show_ui'], 404, 'Unknown content type.');

        return $pt;
    }
}
