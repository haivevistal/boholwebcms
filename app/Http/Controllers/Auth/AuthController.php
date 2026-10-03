<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (! cms_installed()) {
            return redirect()->route('install.notice');
        }

        return Inertia::render('Auth/Login', [
            'canRegister' => (bool) get_option('users_can_register'),
            'redirectTo' => $request->query('redirect_to'),
            'loginMessage' => apply_filters('login_message', null),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
            'redirect_to' => 'nullable|string',
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Plugins can veto (2FA, captcha, lockouts...) by returning a string error.
        $error = apply_filters('authenticate', null, $data['login'], $request);
        if (is_string($error)) {
            throw ValidationException::withMessages(['login' => $error]);
        }

        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            do_action('login_failed', $data['login']);
            throw ValidationException::withMessages(['login' => 'The username/email or password you entered is incorrect.']);
        }

        $request->session()->regenerate();
        do_action('login', $request->user());

        $default = current_user_can('edit_posts') ? route('admin.dashboard') : (current_user_can('read') ? route('admin.profile') : url('/'));
        $redirect = apply_filters('login_redirect', $this->safeRedirect($data['redirect_to'] ?? null) ?? $default, $request->user());

        // Leaving the admin SPA for the themed front end needs a full page load.
        return str_starts_with($redirect, url('/admin')) ? redirect()->intended($redirect) : Inertia::location($redirect);
    }

    public function showRegister()
    {
        abort_unless(get_option('users_can_register'), 404);

        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        abort_unless(get_option('users_can_register'), 404);

        $data = $request->validate([
            'username' => 'required|alpha_dash|min:3|max:60|unique:users,username',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $errors = apply_filters('registration_errors', [], $data, $request);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $user = User::create([
            'username' => $data['username'],
            'name' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => (string) get_option('default_role', 'subscriber'),
        ]);

        do_action('user_register', $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.profile')->with('success', 'Welcome! Your account has been created.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        do_action('logout', $user);

        return Inertia::location(apply_filters('logout_redirect', url('/')));
    }

    /**
     * Logout link usable from themes: /logout?_token={csrf}
     */
    public function logoutGet(Request $request)
    {
        abort_unless(hash_equals((string) $request->session()->token(), (string) $request->query('_token')), 419);

        return $this->logout($request);
    }

    public function installNotice()
    {
        if (cms_installed()) {
            return redirect('/');
        }

        return Inertia::render('Auth/Install');
    }

    protected function safeRedirect(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return url($url);
        }

        return str_starts_with($url, url('/')) ? $url : null;
    }
}
