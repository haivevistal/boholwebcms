<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Front-end AJAX, the equivalent of admin-ajax.php for visitors:
 *
 *   add_action('ajax_add_to_cart', fn (Request $r) => cms_send_json([...]));          // logged-in users
 *   add_action('ajax_nopriv_add_to_cart', fn (Request $r) => cms_send_json([...]));   // guests
 *
 * POST /cms-ajax  { action: "add_to_cart", ... }   (CSRF token required)
 */
class AjaxController extends Controller
{
    public function handle(Request $request)
    {
        $action = sanitize_key($request->input('action'));
        $hook = is_user_logged_in() ? "ajax_{$action}" : "ajax_nopriv_{$action}";

        if ($action === '' || ! has_action($hook)) {
            return response()->json(['success' => false, 'data' => 'Unknown action'], 400);
        }

        do_action($hook, $request);

        return response()->json(['success' => true, 'data' => null]);
    }

    /**
     * /cms-webhook/{action} — no CSRF, for third-party callbacks (payment providers ...).
     *   add_action('webhook_paypal', fn (Request $r) => cms_send_json(['ok' => true]));
     */
    public function webhook(Request $request, string $action)
    {
        $hook = 'webhook_'.sanitize_key($action);
        abort_unless(has_action($hook), 404);

        do_action($hook, $request);

        return response()->json(['success' => true]);
    }
}
