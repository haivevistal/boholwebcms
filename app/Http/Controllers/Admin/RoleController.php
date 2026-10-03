<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\Capabilities;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Capabilities $caps)
    {
        $counts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return Inertia::render('Users/Roles', [
            'roles' => Role::orderBy('id')->get()->map(fn (Role $r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'capabilities' => $r->capabilities ?? [],
                'is_core' => $r->isCore(),
                'users' => $counts[$r->slug] ?? 0,
            ]),
            'capabilityGroups' => $caps->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|alpha_dash|max:100|unique:roles,slug',
            'copy_from' => 'nullable|exists:roles,slug',
        ]);

        $slug = $data['slug'] ?: Str::slug($data['name'], '_');
        abort_if(Role::where('slug', $slug)->exists(), 422, 'A role with that slug already exists.');

        $capabilities = $data['copy_from'] ? (Role::where('slug', $data['copy_from'])->value('capabilities') ?? ['read']) : ['read'];
        $role = Role::create(['slug' => $slug, 'name' => $data['name'], 'capabilities' => $capabilities]);
        do_action('add_role', $role);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role, Capabilities $caps)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'capabilities' => 'array',
            'capabilities.*' => 'string|max:100',
        ]);

        // Keep the administrator role all-powerful so nobody locks themselves out.
        $capabilities = $role->slug === 'administrator' ? ['*'] : array_values(array_unique($data['capabilities'] ?? []));

        $role->update(['name' => $data['name'], 'capabilities' => $capabilities]);
        $caps->flush();
        do_action('update_role', $role);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->isCore(), 422, 'Built-in roles cannot be deleted.');

        $fallback = (string) get_option('default_role', 'subscriber');
        User::where('role', $role->slug)->update(['role' => $fallback === $role->slug ? 'subscriber' : $fallback]);
        $role->delete();
        do_action('remove_role', $role->slug);

        return back()->with('success', 'Role deleted. Its users were moved to the default role.');
    }
}
