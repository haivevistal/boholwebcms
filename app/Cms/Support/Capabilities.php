<?php

namespace App\Cms\Support;

use App\Cms\Content\PostTypeRegistry;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Throwable;

/**
 * Role & capability checks, modelled on WordPress.
 *
 *   current_user_can('manage_options');
 *   current_user_can('edit_post', $post);   // meta capability -> edit_posts / edit_others_posts
 *   add_filter('user_has_cap', fn ($allowed, $cap, $user, $args) => ..., 10, 4);
 */
class Capabilities
{
    /** @var array<string, array<int, string>>|null slug => caps */
    protected ?array $roles = null;

    public const CORE = [
        'General' => ['read'],
        'Posts' => ['edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'read_private_posts'],
        'Pages' => ['edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages', 'read_private_pages'],
        'Taxonomies & Comments' => ['manage_categories', 'moderate_comments'],
        'Media' => ['upload_files', 'unfiltered_html', 'unfiltered_upload'],
        'Appearance' => ['switch_themes', 'edit_theme_options', 'edit_themes', 'install_themes', 'update_themes', 'delete_themes'],
        'Plugins' => ['activate_plugins', 'edit_plugins', 'install_plugins', 'update_plugins', 'delete_plugins'],
        'Users' => ['list_users', 'create_users', 'edit_users', 'delete_users', 'promote_users', 'manage_roles'],
        'Tools & Settings' => ['manage_options', 'import', 'export'],
    ];

    public const DEFAULT_ROLES = [
        'administrator' => ['name' => 'Administrator', 'capabilities' => ['*']],
        'editor' => ['name' => 'Editor', 'capabilities' => [
            'read', 'moderate_comments', 'manage_categories', 'upload_files', 'unfiltered_html',
            'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'read_private_posts',
            'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages', 'read_private_pages',
        ]],
        'author' => ['name' => 'Author', 'capabilities' => [
            'read', 'upload_files', 'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_published_posts',
        ]],
        'contributor' => ['name' => 'Contributor', 'capabilities' => ['read', 'edit_posts', 'delete_posts']],
        'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read']],
    ];

    /**
     * All known capabilities grouped for the Roles screen (plugins extend via "cms_capabilities").
     */
    public function all(): array
    {
        return apply_filters('cms_capabilities', self::CORE);
    }

    public function roleCaps(string $role): array
    {
        $this->loadRoles();

        return $this->roles[$role] ?? [];
    }

    public function roles(): array
    {
        $this->loadRoles();

        return $this->roles;
    }

    public function flush(): void
    {
        $this->roles = null;
    }

    public function userCan(?User $user, string $cap, mixed ...$args): bool
    {
        if (! $user) {
            return apply_filters('user_has_cap', false, $cap, null, $args);
        }

        $required = $this->mapMetaCap($cap, $user, $args);
        $caps = $this->roleCaps($user->role ?? 'subscriber');
        $caps = apply_filters('user_caps', $caps, $user);

        $allowed = true;
        foreach ($required as $req) {
            if ($req === 'do_not_allow' || (! in_array('*', $caps, true) && ! in_array($req, $caps, true))) {
                $allowed = false;
                break;
            }
        }

        return (bool) apply_filters('user_has_cap', $allowed, $cap, $user, $args);
    }

    /**
     * Translate meta capabilities ("edit_post" for a specific post) into
     * primitive capabilities, like WordPress map_meta_cap().
     *
     * @return array<int, string>
     */
    public function mapMetaCap(string $cap, User $user, array $args): array
    {
        $post = $args[0] ?? null;
        if (is_numeric($post)) {
            $post = Post::find($post);
        }

        $types = app(PostTypeRegistry::class);

        $caps = match ($cap) {
            'edit_post', 'delete_post', 'publish_post', 'read_post' => (function () use ($cap, $post, $user, $types) {
                if (! $post instanceof Post) {
                    return ['do_not_allow'];
                }
                $type = $types->get($post->type);
                $c = $type['capabilities'] ?? [];
                $own = (int) $post->author_id === (int) $user->id;

                return match ($cap) {
                    'edit_post' => [$own ? ($c['edit'] ?? 'edit_posts') : ($c['edit_others'] ?? 'edit_others_posts')],
                    'delete_post' => [$own ? ($c['delete'] ?? 'delete_posts') : ($c['delete_others'] ?? 'delete_others_posts')],
                    'publish_post' => [$c['publish'] ?? 'publish_posts'],
                    'read_post' => $post->status === 'private' && ! $own ? [$c['read_private'] ?? 'read_private_posts'] : ['read'],
                };
            })(),
            'edit_user', 'delete_user' => (function () use ($cap, $args, $user) {
                $target = $args[0] ?? null;
                $targetId = $target instanceof User ? $target->id : (int) $target;
                if ($cap === 'edit_user' && $targetId === (int) $user->id) {
                    return ['read'];
                }

                return [$cap === 'edit_user' ? 'edit_users' : 'delete_users'];
            })(),
            'edit_comment' => ['moderate_comments'],
            default => [$cap],
        };

        return apply_filters('map_meta_cap', $caps, $cap, $user, $args);
    }

    protected function loadRoles(): void
    {
        if ($this->roles !== null) {
            return;
        }

        $this->roles = [];
        try {
            foreach (Role::all() as $role) {
                $this->roles[$role->slug] = $role->capabilities ?? [];
            }
        } catch (Throwable) {
            // Table missing (not installed) — fall back to defaults.
        }

        if (! $this->roles) {
            foreach (self::DEFAULT_ROLES as $slug => $def) {
                $this->roles[$slug] = $def['capabilities'];
            }
        }
    }
}
