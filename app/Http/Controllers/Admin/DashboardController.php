<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Admin\DashboardWidgets;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(DashboardWidgets $widgets)
    {
        if (! current_user_can('edit_posts')) {
            return redirect()->route('admin.profile');
        }

        return Inertia::render('Dashboard', [
            'widgets' => $widgets->resolve(),
        ]);
    }

    public function quickDraft(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);

        cms_insert_post([
            'type' => 'post',
            'status' => 'draft',
            'title' => $data['title'] ?? '',
            'content' => isset($data['content']) ? '<p>'.nl2br(e($data['content'])).'</p>' : '',
            'author_id' => current_user_id(),
        ]);

        return back()->with('success', 'Draft saved.');
    }
}
