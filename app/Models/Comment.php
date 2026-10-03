<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    public const STATUSES = [
        'approved' => 'Approved',
        'pending' => 'Pending',
        'spam' => 'Spam',
        'trash' => 'Trash',
    ];

    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'author_name', 'author_email', 'author_url', 'author_ip', 'user_agent', 'content', 'status',
    ];

    protected static function booted(): void
    {
        $recount = fn (Comment $c) => Post::whereKey($c->post_id)->update([
            'comment_count' => static::where('post_id', $c->post_id)->where('status', 'approved')->count(),
        ]);

        static::saved($recount);
        static::deleted($recount);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function avatarUrl(int $size = 48): string
    {
        $hash = md5(strtolower(trim((string) ($this->user?->email ?? $this->author_email))));

        return apply_filters('get_avatar_url', "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp", $this, $size);
    }

    public function toFrontArray(): array
    {
        return apply_filters('comment_data', [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'author' => $this->user?->name ?? $this->author_name ?? 'Anonymous',
            'author_url' => $this->author_url,
            'avatar' => $this->avatarUrl(),
            'content' => apply_filters('comment_text', nl2br(e($this->content)), $this),
            'status' => $this->status,
            'date' => $this->created_at?->toIso8601String(),
            'date_formatted' => format_cms_date($this->created_at, get_option('date_format', 'F j, Y').' '.get_option('time_format', 'g:i a')),
        ], $this);
    }
}
