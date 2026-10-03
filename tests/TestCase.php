<?php

namespace Tests;

use App\Cms\Support\Capabilities;
use App\Models\Option;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed what `php artisan cms:install` would create.
     */
    protected function installCms(array $options = []): User
    {
        foreach (Capabilities::DEFAULT_ROLES as $slug => $role) {
            Role::updateOrCreate(['slug' => $slug], ['name' => $role['name'], 'capabilities' => $role['capabilities']]);
        }
        $defaults = [
            'blogname' => 'Test Site', 'blogdescription' => 'Testing', 'permalink_structure' => '/%postname%',
            'active_theme' => 'aurora', 'active_plugins' => [], 'show_on_front' => 'posts', 'posts_per_page' => 10,
            'default_comment_status' => true, 'require_name_email' => true, 'comment_previously_approved' => false,
        ];
        foreach (array_merge($defaults, $options) as $name => $value) {
            Option::updateOrCreate(['name' => $name], ['value' => json_encode($value), 'autoload' => true]);
        }
        app(\App\Cms\Support\Options::class)->flush();
        app(Capabilities::class)->flush();

        Term::firstOrCreate(['taxonomy' => 'category', 'slug' => 'uncategorized'], ['name' => 'Uncategorized']);

        return User::factory()->administrator()->create(['username' => 'admin']);
    }
}
