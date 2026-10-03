<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = Comment::with(['post:id,title,type,slug,status', 'user:id,name,email', 'parent:id,author_name'])->latest();
        if ($status === 'all') {
            $query->whereIn('status', ['approved', 'pending']);
        } else {
            $query->where('status', $status);
        }
        if ($s = trim((string) $request->query('s'))) {
            $query->where(fn ($q) => $q->where('content', 'like', "%{$s}%")->orWhere('author_name', 'like', "%{$s}%")->orWhere('author_email', 'like', "%{$s}%"));
        }
        if ($postId = $request->query('post_id')) {
            $query->where('post_id', $postId);
        }

        $comments = $query->paginate(20)->withQueryString()->through(fn (Comment $c) => [
            'id' => $c->id,
            'author_name' => $c->user?->name ?? $c->author_name,
            'author_email' => $c->user?->email ?? $c->author_email,
            'author_url' => $c->author_url,
            'author_ip' => $c->author_ip,
            'avatar' => $c->avatarUrl(48),
            'content' => $c->content,
            'status' => $c->status,
            'date' => format_cms_date($c->created_at, 'Y/m/d \a\t g:i a'),
            'in_reply_to' => $c->parent?->author_name,
            'post' => $c->post ? [
                'id' => $c->post->id,
                'title' => $c->post->title,
                'edit_url' => url('/admin/content/'.$c->post->type.'/'.$c->post->id.'/edit'),
                'view_url' => $c->post->permalink,
            ] : null,
        ]);

        $counts = Comment::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Comments/Index', [
            'comments' => $comments,
            'counts' => [
                'all' => ($counts['approved'] ?? 0) + ($counts['pending'] ?? 0),
                'pending' => $counts['pending'] ?? 0,
                'approved' => $counts['approved'] ?? 0,
                'spam' => $counts['spam'] ?? 0,
                'trash' => $counts['trash'] ?? 0,
            ],
            'filters' => ['status' => $status, 's' => $request->query('s'), 'post_id' => $request->query('post_id')],
        ]);
    }

    public function status(Request $request, Comment $comment)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Comment::STATUSES))]]);
        $this->transition($comment, $data['status']);

        return back()->with('success', 'Comment '.strtolower(Comment::STATUSES[$data['status']]).'.');
    }

    public function update(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'content' => 'required|string',
            'author_name' => 'nullable|string|max:191',
            'author_email' => 'nullable|email|max:191',
            'author_url' => 'nullable|url|max:191',
        ]);
        $comment->update($data);
        do_action('edit_comment', $comment);

        return back()->with('success', 'Comment updated.');
    }

    public function reply(Request $request, Comment $comment)
    {
        $data = $request->validate(['content' => 'required|string|max:65000']);
        $user = $request->user();

        $reply = Comment::create([
            'post_id' => $comment->post_id,
            'parent_id' => $comment->id,
            'user_id' => $user->id,
            'author_name' => $user->name,
            'author_email' => $user->email,
            'author_url' => $user->website,
            'author_ip' => $request->ip(),
            'content' => $data['content'],
            'status' => 'approved',
        ]);

        // Replying approves the parent, like WordPress.
        if ($comment->status === 'pending') {
            $this->transition($comment, 'approved');
        }

        do_action('comment_post', $reply, 'approved');

        return back()->with('success', 'Reply posted.');
    }

    public function destroy(Comment $comment)
    {
        do_action('delete_comment', $comment);
        Comment::where('parent_id', $comment->id)->update(['parent_id' => $comment->parent_id]);
        $comment->delete();
        do_action('deleted_comment', $comment->id);

        return back()->with('success', 'Comment permanently deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:approved,pending,spam,trash,delete',
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $comments = Comment::whereIn('id', $data['ids'])->get();
        foreach ($comments as $comment) {
            if ($data['action'] === 'delete') {
                do_action('delete_comment', $comment);
                $comment->delete();
            } else {
                $this->transition($comment, $data['action']);
            }
        }

        return back()->with('success', $comments->count().' comment(s) updated.');
    }

    protected function transition(Comment $comment, string $status): void
    {
        $old = $comment->status;
        if ($old === $status) {
            return;
        }
        $comment->update(['status' => $status]);
        do_action('transition_comment_status', $status, $old, $comment);
        do_action("comment_{$status}", $comment);
    }
}
