<?php

namespace App\Models;

use App\Cms\Support\Permalinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Term extends Model
{
    protected $fillable = ['taxonomy', 'name', 'slug', 'description', 'parent_id'];

    protected function casts(): array
    {
        return ['count' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Term $term) {
            $base = Str::slug($term->slug ?: $term->name) ?: 'term';
            $slug = $base;
            $i = 2;
            while (static::where('taxonomy', $term->taxonomy)->where('slug', $slug)
                ->when($term->exists, fn ($q) => $q->where('id', '!=', $term->id))->exists()) {
                $slug = $base.'-'.$i++;
            }
            $term->slug = $slug;
        });
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'term_relationships');
    }

    public function parent()
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Term::class, 'parent_id');
    }

    public function getLinkAttribute(): string
    {
        return app(Permalinks::class)->term($this);
    }

    public function toFrontArray(): array
    {
        return [
            'id' => $this->id,
            'taxonomy' => $this->taxonomy,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'count' => $this->count,
            'parent_id' => $this->parent_id,
            'link' => $this->link,
        ];
    }

    /**
     * Recalculate the number of published posts per term.
     */
    public static function recount(array $ids): void
    {
        foreach (array_filter($ids) as $id) {
            $count = DB::table('term_relationships')
                ->join('posts', 'posts.id', '=', 'term_relationships.post_id')
                ->where('term_relationships.term_id', $id)
                ->where('posts.status', 'publish')
                ->count();
            static::whereKey($id)->update(['count' => $count]);
        }
    }
}
