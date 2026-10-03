<?php

namespace App\Cms\Content;

use Illuminate\Support\Str;

/**
 *   register_taxonomy('product_cat', 'product', [
 *       'label' => 'Product Categories',
 *       'hierarchical' => true,
 *       'rewrite' => ['slug' => 'product-category'],
 *   ]);
 */
class TaxonomyRegistry
{
    /** @var array<string, array> */
    protected array $taxonomies = [];

    public function register(string $name, string|array $objectTypes, array $args = []): array
    {
        $label = $args['label'] ?? Str::headline(Str::plural($name));
        $singular = $args['labels']['singular_name'] ?? Str::headline(Str::singular($label));

        $tax = array_replace([
            'name' => $name,
            'label' => $label,
            'object_types' => (array) $objectTypes,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => $name],
            'capability' => 'manage_categories',
            'builtin' => false,
        ], $args);

        if ($tax['rewrite'] !== false) {
            $tax['rewrite'] = array_merge(['slug' => $name], (array) $tax['rewrite']);
        }

        $tax['labels'] = array_merge([
            'name' => $label,
            'singular_name' => $singular,
            'add_new_item' => 'Add New '.$singular,
            'edit_item' => 'Edit '.$singular,
            'search_items' => 'Search '.$label,
            'menu_name' => $label,
            'not_found' => 'No '.Str::lower($label).' found.',
        ], $args['labels'] ?? []);

        $tax = apply_filters('register_taxonomy_args', $tax, $name);
        $this->taxonomies[$name] = $tax;

        do_action('registered_taxonomy', $name, $tax);

        return $tax;
    }

    public function attach(string $taxonomy, string $postType): void
    {
        if (isset($this->taxonomies[$taxonomy]) && ! in_array($postType, $this->taxonomies[$taxonomy]['object_types'], true)) {
            $this->taxonomies[$taxonomy]['object_types'][] = $postType;
        }
    }

    public function get(string $name): ?array
    {
        return $this->taxonomies[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        return isset($this->taxonomies[$name]);
    }

    public function all(): array
    {
        return $this->taxonomies;
    }

    /** @return array<string, array> */
    public function forType(string $postType): array
    {
        $declared = function_exists('app') && app()->bound(PostTypeRegistry::class)
            ? (app(PostTypeRegistry::class)->get($postType)['taxonomies'] ?? [])
            : [];

        return array_filter(
            $this->taxonomies,
            fn ($t) => in_array($postType, $t['object_types'], true) || in_array($t['name'], $declared, true)
        );
    }

    public function byRewriteSlug(string $slug): ?array
    {
        foreach ($this->taxonomies as $tax) {
            if ($tax['public'] && $tax['rewrite'] !== false && ($tax['rewrite']['slug'] ?? null) === $slug) {
                return $tax;
            }
        }

        return null;
    }

    public function forClient(string $name): ?array
    {
        $t = $this->get($name);

        return $t ? [
            'name' => $t['name'],
            'label' => $t['label'],
            'labels' => $t['labels'],
            'hierarchical' => $t['hierarchical'],
            'object_types' => $t['object_types'],
        ] : null;
    }
}
