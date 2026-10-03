<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $query = User::query()->withCount('posts');
        if ($role) {
            $query->where('role', $role);
        }
        if ($s = trim((string) $request->query('s'))) {
            $query->where(fn ($q) => $q->where('username', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }

        $sortable = ['username', 'name', 'email', 'created_at'];
        $orderby = in_array($request->query('orderby'), $sortable, true) ? $request->query('orderby') : 'username';
        $query->orderBy($orderby, $request->query('order') === 'desc' ? 'desc' : 'asc');

        $roles = Role::orderBy('id')->get(['slug', 'name']);
        $roleNames = $roles->pluck('name', 'slug');

        $users = $query->paginate(20)->withQueryString()->through(fn (User $u) => [
            'id' => $u->id,
            'username' => $u->username,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'role_name' => $roleNames[$u->role] ?? $u->role,
            'posts_count' => $u->posts_count,
            'avatar' => $u->avatarUrl(48),
            'registered' => format_cms_date($u->created_at),
            'is_self' => $u->id === current_user_id(),
            'custom' => apply_filters('manage_users_custom_columns', [], $u),
        ]);

        $counts = User::select('role', DB::raw('count(*) as total'))->groupBy('role')->pluck('total', 'role');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'counts' => $counts,
            'total' => User::count(),
            'filters' => $request->only(['role', 's', 'orderby', 'order']),
            'extraColumns' => apply_filters('manage_users_columns', []),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Edit', [
            'user' => null,
            'roles' => $this->assignableRoles(),
            'defaultRole' => get_option('default_role', 'subscriber'),
            'extraFields' => $this->extraFields(null),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|alpha_dash|max:60|unique:users,username',
            'email' => 'required|email|max:190|unique:users,email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:190',
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in($this->assignableRoles()->pluck('slug'))],
            'meta' => 'nullable|array',
        ]);

        $user = User::create([
            ...collect($data)->except('meta')->all(),
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $data['username'],
        ]);

        $this->saveMeta($user, $data['meta'] ?? []);
        do_action('user_register', $user);

        return redirect()->route('admin.users.index')->with('success', 'New user created.');
    }

    public function edit(User $user)
    {
        $this->authorizeCap('edit_user', $user);

        return Inertia::render('Users/Edit', [
            'user' => $this->present($user),
            'roles' => $this->assignableRoles(),
            'extraFields' => $this->extraFields($user),
            'mode' => 'edit',
            'canChangeRole' => current_user_can('promote_users') && $user->id !== current_user_id(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeCap('edit_user', $user);
        $this->persist($request, $user, current_user_can('promote_users') && $user->id !== current_user_id());

        return back()->with('success', 'User updated.');
    }

    public function profile()
    {
        $user = current_user();

        return Inertia::render('Users/Edit', [
            'user' => $this->present($user),
            'roles' => $this->assignableRoles(),
            'extraFields' => $this->extraFields($user),
            'mode' => 'profile',
            'canChangeRole' => false,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $this->persist($request, current_user(), false);

        return back()->with('success', 'Profile updated.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === current_user_id(), 422, 'You cannot delete your own account here.');
        $this->deleteUser($user, $request->input('reassign'));

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string',
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'role' => 'nullable|string',
            'reassign' => 'nullable|integer',
        ]);
        $users = User::whereIn('id', $data['ids'])->where('id', '!=', current_user_id())->get();

        if ($data['action'] === 'delete') {
            $this->authorizeCap('delete_users');
            $users->each(fn ($u) => $this->deleteUser($u, $data['reassign'] ?? null));
        } elseif ($data['action'] === 'role') {
            $this->authorizeCap('promote_users');
            abort_unless($this->assignableRoles()->pluck('slug')->contains($data['role']), 422);
            $users->each(function ($u) use ($data) {
                $old = $u->role;
                $u->update(['role' => $data['role']]);
                do_action('set_user_role', $u, $data['role'], $old);
            });
        }

        return back()->with('success', $users->count().' user(s) updated.');
    }

    /* ------------------------------------------------------------------ */

    protected function persist(Request $request, User $user, bool $canChangeRole): void
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'name' => 'required|string|max:190',
            'website' => 'nullable|url|max:190',
            'bio' => 'nullable|string|max:5000',
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['nullable', Rule::in($this->assignableRoles()->pluck('slug'))],
            'meta' => 'nullable|array',
        ]);

        $old = $user->role;
        $user->fill(collect($data)->only(['email', 'first_name', 'last_name', 'name', 'website', 'bio'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($canChangeRole && ! empty($data['role'])) {
            $user->role = $data['role'];
        }
        $user->save();

        $this->saveMeta($user, $data['meta'] ?? []);

        if ($old !== $user->role) {
            do_action('set_user_role', $user, $user->role, $old);
        }
        do_action('profile_update', $user);
    }

    protected function deleteUser(User $user, mixed $reassign): void
    {
        do_action('delete_user', $user, $reassign);

        if ($reassign && User::whereKey($reassign)->exists()) {
            Post::where('author_id', $user->id)->update(['author_id' => $reassign]);
        } else {
            Post::where('author_id', $user->id)->update(['status' => 'trash']);
        }
        $user->delete();

        do_action('deleted_user', $user->id);
    }

    /**
     * Extra profile fields added by plugins:
     *   add_filter('user_profile_fields', fn ($f) => [...$f, ['name' => 'twitter', 'label' => 'Twitter']]);
     */
    protected function extraFields(?User $user): array
    {
        $fields = apply_filters('user_profile_fields', [], $user);

        return array_map(function ($f) use ($user) {
            $f = array_merge(['type' => 'text', 'description' => null, 'choices' => []], $f);
            $f['value'] = $user ? $user->getMeta($f['name']) : null;

            return $f;
        }, $fields);
    }

    protected function saveMeta(User $user, array $meta): void
    {
        $allowed = array_column(apply_filters('user_profile_fields', [], $user), 'name');
        foreach ($meta as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $user->setMeta($key, is_string($value) ? sanitize_textarea_field($value) : $value);
            }
        }
    }

    protected function present(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'website' => $user->website,
            'bio' => $user->bio,
            'role' => $user->role,
            'avatar' => $user->avatarUrl(96),
            'registered' => format_cms_date($user->created_at),
        ];
    }

    protected function assignableRoles()
    {
        $roles = Role::orderBy('id')->get(['slug', 'name']);

        // Only administrators may hand out the administrator role.
        if (! current_user_can('manage_roles')) {
            $roles = $roles->reject(fn ($r) => $r->slug === 'administrator')->values();
        }

        return apply_filters('editable_roles', $roles);
    }
}
