<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Front-end comment submission (wp-comments-post.php).
 */
class CommentController extends Controller
{
    public function store(Request $request)
    {
        $user = current_user();
        $requireNameEmail = (bool) get_option('require_name_email', true);

        $data = $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'parent_id' => 'nullable|integer|exists:comments,id',
            'content' => 'required|string|max:65525',
            'author_name' => ($user || ! $requireNameEmail ? 'nullable' : 'required').'|string|max:191',
            'author_email' => ($user || ! $requireNameEmail ? 'nullable' : 'required').'|email|max:191',
            'author_url' => 'nullable|url|max:191',
            'website' => 'nullable|max:0', // honeypot
        ]);

        $post = Post::findOrFail($data['post_id']);

        if (! $post->isPubliclyViewable() || $post->comment_status !== 'open' || ! apply_filters('comments_open', true, $post)) {
            throw ValidationException::withMessages(['content' => 'Comments are closed.']);
        }
        if (get_option('comment_registration') && ! $user) {
            throw ValidationException::withMessages(['content' => 'You must be logged in to post a comment.']);
        }

        $comment = new Comment([
            'post_id' => $post->id,
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => $user?->id,
            'author_name' => $user?->name ?? $data['author_name'] ?? 'Anonymous',
            'author_email' => $user?->email ?? $data['author_email'] ?? null,
            'author_url' => $user?->website ?? $data['author_url'] ?? null,
            'author_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250),
            'content' => trim($data['content']),
        ]);

        $comment->status = $this->moderate($comment);
        $comment = apply_filters('preprocess_comment', $comment, $request);
        $comment->save();

        if ($comment->status === 'pending') {
            $request->session()->push('my_pending_comments', $comment->id);
        }

        do_action('comment_post', $comment, $comment->status);

        $message = $comment->status === 'approved' ? 'Thanks for your comment!' : 'Your comment is awaiting moderation.';

        return redirect()->to($post->permalink.'#comment-'.$comment->id)->with('success', $message);
    }

    /**
     * Decide approved/pending/spam/trash using Discussion settings (check_comment()).
     */
    protected function moderate(Comment $comment): string
    {
        if (current_user_can('moderate_comments')) {
            return 'approved';
        }

        $text = implode("\n", [$comment->author_name, $comment->author_email, $comment->author_url, $comment->content, $comment->author_ip, $comment->user_agent]);

        foreach (preg_split('/\R/', (string) get_option('disallowed_keys', '')) as $word) {
            $word = trim($word);
            if ($word !== '' && stripos($text, $word) !== false) {
                return 'trash';
            }
        }

        $status = 'approved';

        if (get_option('comment_moderation')) {
            $status = 'pending';
        }

        $maxLinks = (int) get_option('comment_max_links', 2);
        if ($maxLinks && preg_match_all('/<a |https?:\/\//i', $comment->content) >= $maxLinks) {
            $status = 'pending';
        }

        foreach (preg_split('/\R/', (string) get_option('moderation_keys', '')) as $word) {
            $word = trim($word);
            if ($word !== '' && stripos($text, $word) !== false) {
                $status = 'pending';
            }
        }

        if ($status === 'approved' && get_option('comment_previously_approved', true) && ! $comment->user_id) {
            $known = Comment::where('author_email', $comment->author_email)->where('status', 'approved')->exists();
            if (! $known) {
                $status = 'pending';
            }
        }

        return (string) apply_filters('pre_comment_approved', $status, $comment);
    }
}
