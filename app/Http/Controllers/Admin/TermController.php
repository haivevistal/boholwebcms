<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TermController extends Controller
{
    public function __construct(protected TaxonomyRegistry $taxonomies) {}

    public function index(Request $request, string $taxonomy)
    {
        $tax = $this->tax($taxonomy);
        $this->authorizeCap($tax['capability']);

        $query = Term::where('taxonomy', $taxonomy);
        if ($s = trim((string) $request->query('s'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"));
        }

        $terms = $query->orderBy('name')->get();

        // Hierarchical taxonomies are shown as an indented tree.
        if ($tax['hierarchical'] && ! $request->query('s')) {
            $terms = $this->tree($terms);
        } else {
            $terms = $terms->map(fn ($t) => $t->toFrontArray() + ['depth' => 0]);
        }

        $postType = $request->query('post_type', $tax['object_types'][0] ?? 'post');

        return Inertia::render('Terms/Index', [
            'taxonomy' => $this->taxonomies->forClient($taxonomy),
            'terms' => $terms->values(),
            'parents' => $tax['hierarchical'] ? Term::where('taxonomy', $taxonomy)->orderBy('name')->get(['id', 'name', 'parent_id']) : [],
            'postType' => app(PostTypeRegistry::class)->forClient($postType),
            'defaultTermId' => $taxonomy === 'category' ? (int) get_option('default_category') : null,
            'filters' => $request->only('s'),
            'extraFields' => apply_filters("{$taxonomy}_term_fields", []),
        ]);
    }

    public function json(Request $request, string $taxonomy)
    {
        $this->tax($taxonomy);
        $q = trim((string) $request->query('q'));

        return Term::where('taxonomy', $taxonomy)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByDesc('count')->limit(20)->get(['id', 'name', 'slug', 'parent_id']);
    }

    public function store(Request $request, string $taxonomy)
    {
        $tax = $this->tax($taxonomy);
        $this->authorizeCap($tax['capability']);

        $data = $this->validated($request, $taxonomy);
        $term = Term::create(['taxonomy' => $taxonomy] + $data);
        do_action('created_term', $term, $taxonomy);
        do_action("created_{$taxonomy}", $term);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($term->toFrontArray());
        }

        return back()->with('success', $tax['labels']['singular_name'].' added.');
    }

    public function update(Request $request, string $taxonomy, Term $term)
    {
        $tax = $this->tax($taxonomy);
        $this->authorizeCap($tax['capability']);
        abort_unless($term->taxonomy === $taxonomy, 404);

        $data = $this->validated($request, $taxonomy, $term);
        if (($data['parent_id'] ?? null) == $term->id) {
            $data['parent_id'] = null;
        }
        $term->update($data);
        do_action('edited_term', $term, $taxonomy);
        do_action("edited_{$taxonomy}", $term);

        return back()->with('success', $tax['labels']['singular_name'].' updated.');
    }

    public function destroy(string $taxonomy, Term $term)
    {
        $tax = $this->tax($taxonomy);
        $this->authorizeCap($tax['capability']);
        abort_unless($term->taxonomy === $taxonomy, 404);

        $this->deleteTerm($term);

        return back()->with('success', $tax['labels']['singular_name'].' deleted.');
    }

    public function bulk(Request $request, string $taxonomy)
    {
        $tax = $this->tax($taxonomy);
        $this->authorizeCap($tax['capability']);
        $data = $request->validate(['action' => 'required|in:delete', 'ids' => 'required|array', 'ids.*' => 'integer']);

        $count = 0;
        foreach (Term::where('taxonomy', $taxonomy)->whereIn('id', $data['ids'])->get() as $term) {
            if ($this->deleteTerm($term)) {
                $count++;
            }
        }

        return back()->with('success', "{$count} item(s) deleted.");
    }

    /* ------------------------------------------------------------------ */

    protected function deleteTerm(Term $term): bool
    {
        // The default category can't be deleted (WordPress behaviour).
        if ($term->taxonomy === 'category' && (int) get_option('default_category') === $term->id) {
            return false;
        }

        do_action('pre_delete_term', $term);

        DB::transaction(function () use ($term) {
            Term::where('parent_id', $term->id)->update(['parent_id' => $term->parent_id]);

            // Posts left without a category fall back to the default one.
            if ($term->taxonomy === 'category' && ($default = (int) get_option('default_category'))) {
                $postIds = $term->posts()->pluck('posts.id');
                $term->posts()->detach();
                foreach ($postIds as $postId) {
                    $hasOther = DB::table('term_relationships')->join('terms', 'terms.id', '=', 'term_relationships.term_id')
                        ->where('post_id', $postId)->where('terms.taxonomy', 'category')->exists();
                    if (! $hasOther) {
                        DB::table('term_relationships')->insertOrIgnore(['post_id' => $postId, 'term_id' => $default]);
                    }
                }
                Term::recount([$default]);
            }

            $term->delete();
        });

        do_action('delete_term', $term->id, $term);

        return true;
    }

    protected function validated(Request $request, string $taxonomy, ?Term $term = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:terms,id',
        ]);
        $data['slug'] = $data['slug'] ?? '';

        return apply_filters('pre_insert_term', $data, $taxonomy, $term);
    }

    protected function tree($terms, $parent = null, int $depth = 0)
    {
        $out = collect();
        foreach ($terms->where('parent_id', $parent) as $term) {
            $out->push($term->toFrontArray() + ['depth' => $depth]);
            $out = $out->merge($this->tree($terms, $term->id, $depth + 1));
        }

        // Orphans (parent deleted) at root level.
        if ($parent === null && $depth === 0) {
            $ids = $out->pluck('id')->all();
            foreach ($terms as $term) {
                if (! in_array($term->id, $ids, true)) {
                    $out->push($term->toFrontArray() + ['depth' => 0]);
                }
            }
        }

        return $out;
    }

    protected function tax(string $taxonomy): array
    {
        $tax = $this->taxonomies->get($taxonomy);
        abort_unless($tax && $tax['show_ui'], 404);

        return $tax;
    }
}
