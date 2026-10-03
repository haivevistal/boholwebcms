<?php

namespace App\Cms\Admin;

/**
 * Cards on Tools → Available Tools.
 *
 *   register_tool('regenerate-thumbnails', [
 *       'title' => 'Regenerate Thumbnails',
 *       'description' => 'Rebuild all image sizes.',
 *       'icon' => 'images',
 *       'callback' => fn () => '<form method="post" ...>',   // served at /admin/page/regenerate-thumbnails
 *   ]);
 *
 * Use 'url' instead of 'callback' to link anywhere.
 */
class ToolsRegistry
{
    protected array $tools = [];

    public function add(string $slug, array $args): void
    {
        $this->tools[$slug] = array_merge([
            'slug' => $slug,
            'title' => $slug,
            'description' => null,
            'icon' => 'wrench',
            'capability' => 'manage_options',
            'url' => null,
            'callback' => null,
            'action_label' => 'Open',
        ], $args);
    }

    public function remove(string $slug): void
    {
        unset($this->tools[$slug]);
    }

    public function all(): array
    {
        return $this->tools;
    }

    public function resolve(): array
    {
        $tools = apply_filters('available_tools', $this->tools);

        return array_values(array_map(function ($t) {
            $t['url'] = $t['url'] ?? url('/admin/page/'.$t['slug']);
            unset($t['callback'], $t['capability']);

            return $t;
        }, array_filter($tools, fn ($t) => current_user_can($t['capability']))));
    }
}
