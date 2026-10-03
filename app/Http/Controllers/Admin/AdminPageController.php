<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Admin\AdminMenu;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;


/**
 * Serves admin pages registered by plugins/themes with add_menu_page(),
 * add_submenu_page() or add_admin_page().
 *
 * The callback receives the Request and may return:
 *   - a string of HTML (rendered inside the admin layout, like WordPress)
 *   - ['component' => 'my-plugin/Settings', 'props' => [...]]  a React component
 *     registered from a plugin script with CMS.registerAdminComponent()
 *   - ['html' => '...', 'title' => 'Custom title']
 *   - any Response (redirect, download, Inertia::render of your own page...)
 */
class AdminPageController extends Controller
{
    public function show(Request $request, string $slug, AdminMenu $menu)
    {
        $page = $menu->page($slug) ?? abort(404, 'Admin page not found.');
        $this->authorizeCap($page['capability']);

        do_action("load-{$slug}", $request);

        $result = call_user_func($page['callback'], $request);

        if ($result instanceof Response || $result instanceof \Illuminate\Contracts\Support\Responsable) {
            return $result;
        }

        $payload = ['title' => $page['title'], 'type' => 'html', 'html' => '', 'component' => null, 'props' => (object) []];

        if (is_array($result)) {
            if (isset($result['component'])) {
                $payload['type'] = 'component';
                $payload['component'] = $result['component'];
                $payload['props'] = (object) ($result['props'] ?? []);
            } else {
                $payload['html'] = (string) ($result['html'] ?? '');
            }
            $payload['title'] = $result['title'] ?? $payload['title'];
        } else {
            $payload['html'] = (string) $result;
        }

        // Non-GET requests re-render the page (WordPress style form handling).
        // Plugins typically redirect back with a flash message instead.

        return Inertia::render('PluginPage', [
            'page' => $payload,
            'slug' => $slug,
            'parent' => $page['parent'],
        ]);
    }

    /**
     * POST /admin/admin-post  with "action" — like admin-post.php:
     *
     *   add_action('admin_post_shop_export', function (Request $request) {
     *       // ... do work, then optionally:
     *       cms_send_response(redirect()->back()->with('success', 'Exported!'));
     *   });
     *
     * Without an explicit response the user is sent back to the previous page.
     */
    public function adminPost(Request $request)
    {
        $action = sanitize_key($request->input('action'));
        abort_if($action === '' || ! has_action("admin_post_{$action}"), 400, 'Unknown action.');

        do_action("admin_post_{$action}", $request);

        return back()->with('success', 'Done.');
    }

    /**
     * POST /admin/ajax  with "action" — like admin-ajax.php:
     *
     *   add_action('admin_ajax_shop_stats', function (Request $request) {
     *       cms_send_json(['total' => 42]);
     *   });
     */
    public function ajax(Request $request)
    {
        $action = sanitize_key($request->input('action'));
        abort_if($action === '' || ! has_action("admin_ajax_{$action}"), 400, 'Unknown action.');

        do_action("admin_ajax_{$action}", $request);

        return response()->json(['success' => true, 'data' => null]);
    }
}
