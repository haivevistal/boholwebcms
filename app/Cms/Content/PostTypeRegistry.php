<?php

namespace App\Cms\Content;

use Illuminate\Support\Str;

/**
 *   register_post_type('product', [
 *       'label' => 'Products',
 *       'labels' => ['singular_name' => 'Product'],
 *       'public' => true,
 *       'has_archive' => true,
 *       'rewrite' => ['slug' => 'shop'],
 *       'menu_icon' => 'shopping-bag',
 *       'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
 *       'taxonomies' => ['product_cat'],
 *   ]);
 *
 * A registered type automatically gets an admin menu (list + editor), front-end
 * single/archive URLs and template hierarchy entries (single-product, archive-product).
 */
class PostTypeRegistry
{
    /** @var array<string, array> */
    protected array $types = [];

    public function register(string $name, array $args = []): array
    {
        $name = Str::lower($name);
        $label = $args['label'] ?? Str::headline(Str::plural($name));
        $singular = $args['labels']['singular_name'] ?? Str::headline(Str::singular($label));

        $defaults = [
            'name' => $name,
            'label' => $label,
            'description' => '',
            'public' => true,
            'show_ui' => null,
            'show_in_menu' => null,
            'menu_icon' => 'file-text',
            'menu_position' => null,
            'hierarchical' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => $name],
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'],
            'taxonomies' => [],
            'capability_type' => 'post',
            'capabilities' => [],
            'exclude_from_search' => null,
            'builtin' => false,
        ];

        $type = array_replace($defaults, $args);
        $type['show_ui'] ??= $type['public'];
        $type['show_in_menu'] ??= $type['show_ui'];
        $type['exclude_from_search'] ??= ! $type['public'];
        if ($type['rewrite'] !== false) {
            $type['rewrite'] = array_merge(['slug' => $name], (array) $type['rewrite']);
        }

        $type['labels'] = array_merge([
            'name' => $label,
            'singular_name' => $singular,
            'add_new' => 'Add '.$singular,
            'add_new_item' => 'Add New '.$singular,
            'edit_item' => 'Edit '.$singular,
            'new_item' => 'New '.$singular,
            'view_item' => 'View '.$singular,
            'search_items' => 'Search '.$label,
            'not_found' => 'No '.Str::lower($label).' found.',
            'all_items' => 'All '.$label,
            'menu_name' => $label,
        ], $args['labels'] ?? []);

        $plural = $type['capability_type'] === 'post' ? 'posts' : ($type['capability_type'] === 'page' ? 'pages' : Str::plural($type['capability_type']));
        $type['capabilities'] = array_merge([
            'edit' => "edit_{$plural}",
            'edit_others' => "edit_others_{$plural}",
            'publish' => "publish_{$plural}",
            'delete' => "delete_{$plural}",
            'delete_others' => "delete_others_{$plural}",
            'read_private' => "read_private_{$plural}",
        ], $type['capabilities']);

        $type = apply_filters('register_post_type_args', $type, $name);

        $this->types[$name] = $type;

        // Remember custom capabilities so the Roles screen can assign them.
        if (! in_array($type['capability_type'], ['post', 'page'], true)) {
            add_filter('cms_capabilities', function ($caps) use ($type) {
                $caps[$type['label']] = array_values($type['capabilities']);

                return $caps;
            });
        }

        do_action('registered_post_type', $name, $type);

        return $type;
    }

    public function unregister(string $name): void
    {
        unset($this->types[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->types[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /** @return array<string, array> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string, array> */
    public function public(): array
    {
        return array_filter($this->types, fn ($t) => $t['public']);
    }

    public function supports(string $name, string $feature): bool
    {
        return in_array($feature, $this->types[$name]['supports'] ?? [], true);
    }

    /**
     * Post type whose rewrite slug matches the first URL segment.
     */
    public function byRewriteSlug(string $slug): ?array
    {
        foreach ($this->types as $type) {
            if ($type['builtin'] || ! $type['public'] || $type['rewrite'] === false) {
                continue;
            }
            if (($type['rewrite']['slug'] ?? null) === $slug) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Strip callbacks before sending a type definition to the browser.
     */
    public function forClient(string $name): ?array
    {
        $type = $this->get($name);
        if (! $type) {
            return null;
        }

        return [
            'name' => $type['name'],
            'label' => $type['label'],
            'labels' => $type['labels'],
            'hierarchical' => $type['hierarchical'],
            'supports' => array_values($type['supports']),
            'taxonomies' => array_values($type['taxonomies']),
            'public' => $type['public'],
            'menu_icon' => $type['menu_icon'],
        ];
    }
}
