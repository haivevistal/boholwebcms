<?php

namespace App\Cms\Admin;

/**
 * The admin sidebar. Every item — including the core ones — is registered
 * through this class during the "admin_menu" action, so plugins can add,
 * reorder or remove anything:
 *
 *   add_action('admin_menu', function () {
 *       add_menu_page('Shop', 'Shop', 'manage_options', 'shop', fn () => '<h2>Hello</h2>', 'shopping-cart', 26);
 *       add_submenu_page('tools', 'Cache', 'Clear Cache', 'manage_options', 'my-cache', [MyCache::class, 'page']);
 *       add_submenu_page('settings', 'Shop', 'Shop', 'manage_options', 'shop-settings', fn () => ...);
 *       remove_submenu_page('tools', 'available-tools');
 *   });
 */
class AdminMenu
{
    /** @var array<string, array> */
    protected array $menus = [];

    /** @var array<string, array<string, array>> parent slug => children */
    protected array $submenus = [];

    /** @var array<string, array> admin pages served at /admin/page/{slug} */
    protected array $pages = [];

    /** @var array<string, true> */
    protected array $removedMenus = [];

    /** @var array<string, array<string, true>> */
    protected array $removedSubmenus = [];

    protected int $sequence = 0;

    protected bool $built = false;

    /**
     * Same signature as WordPress add_menu_page().
     *
     * @param  callable|array|string|null  $callback  returns an HTML string, or
     *         ['component' => 'vendor/Name', 'props' => [...]] for a React component,
     *         or an Inertia/HTTP response.
     * @param  string|null  $icon  a lucide icon name (e.g. "shopping-cart"), an image URL or a data: URI
     */
    public function addMenuPage(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        callable|array|string|null $callback = null,
        ?string $icon = null,
        int|float|null $position = null,
        array $extra = []
    ): string {
        $this->menus[$slug] = array_merge([
            'slug' => $slug,
            'page_title' => $pageTitle,
            'title' => $menuTitle,
            'capability' => $capability,
            'icon' => $icon ?: 'puzzle',
            'position' => $position ?? 100 + (++$this->sequence) / 1000,
            'url' => $this->isUrl($slug) ? $slug : null,
            'badge' => null,
            'separator_before' => false,
        ], $extra);

        if ($callback !== null) {
            $this->addPage($slug, $pageTitle, $capability, $callback, $slug);
        }

        return $slug;
    }

    /**
     * Same signature as WordPress add_submenu_page().
     */
    public function addSubmenuPage(
        string $parentSlug,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        callable|array|string|null $callback = null,
        int|float|null $position = null,
        array $extra = []
    ): string {
        $this->sequence++;

        $this->submenus[$parentSlug][$slug] = array_merge([
            'slug' => $slug,
            'page_title' => $pageTitle,
            'title' => $menuTitle,
            'capability' => $capability,
            'position' => $position ?? 1000 + $this->sequence,
            'url' => $this->isUrl($slug) ? $slug : null,
            'badge' => null,
        ], $extra);

        if ($callback !== null) {
            $this->addPage($slug, $pageTitle, $capability, $callback, $parentSlug);
        }

        return $slug;
    }

    /**
     * Register an admin page that has no menu entry (like WordPress pages
     * registered with a null parent). Reachable at /admin/page/{slug}.
     */
    public function addPage(string $slug, string $title, string $capability, callable|array|string $callback, ?string $parent = null): void
    {
        $this->pages[$slug] = [
            'slug' => $slug,
            'title' => $title,
            'capability' => $capability,
            'callback' => $callback,
            'parent' => $parent,
        ];
    }

    public function removeMenuPage(string $slug): void
    {
        $this->removedMenus[$slug] = true;
    }

    public function removeSubmenuPage(string $parentSlug, string $slug): void
    {
        $this->removedSubmenus[$parentSlug][$slug] = true;
    }

    public function page(string $slug): ?array
    {
        $this->build();

        return $this->pages[$slug] ?? null;
    }

    public function menu(string $slug): ?array
    {
        return $this->menus[$slug] ?? null;
    }

    public function hasMenu(string $slug): bool
    {
        return isset($this->menus[$slug]);
    }

    /**
     * Runs the admin_menu action once per request.
     */
    public function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        do_action('_core_admin_menu', $this);
        do_action('admin_menu', $this);
    }

    /**
     * The final menu tree for the current user.
     */
    public function toArray(): array
    {
        $this->build();

        $items = [];

        foreach ($this->menus as $slug => $menu) {
            if (isset($this->removedMenus[$slug]) || ! current_user_can($menu['capability'])) {
                continue;
            }

            $children = [];
            foreach ($this->submenus[$slug] ?? [] as $childSlug => $child) {
                if (isset($this->removedSubmenus[$slug][$childSlug]) || ! current_user_can($child['capability'])) {
                    continue;
                }
                $child['url'] = $child['url'] ?? $this->pageUrl($childSlug);
                $children[] = $child;
            }

            // A top-level page with its own callback is listed as the first child.
            if ($children && isset($this->pages[$slug]) && ! collect($children)->contains('slug', $slug)) {
                array_unshift($children, [
                    'slug' => $slug,
                    'title' => $menu['title'],
                    'page_title' => $menu['page_title'],
                    'capability' => $menu['capability'],
                    'position' => -1,
                    'url' => $this->pageUrl($slug),
                    'badge' => null,
                ]);
            }

            usort($children, fn ($a, $b) => $a['position'] <=> $b['position']);

            $menu['url'] = $menu['url']
                ?? (isset($this->pages[$slug]) ? $this->pageUrl($slug) : ($children[0]['url'] ?? '#'));
            $menu['children'] = array_values($children);

            // Skip empty containers the user can't use.
            if ($menu['url'] === '#' && ! $children) {
                continue;
            }

            $items[] = $menu;
        }

        usort($items, fn ($a, $b) => $a['position'] <=> $b['position']);

        $items = apply_filters('admin_menu_items', $items);

        // Strip internal keys before sending to the browser.
        return array_map(function ($item) {
            unset($item['capability']);
            $item['children'] = array_map(function ($c) {
                unset($c['capability']);

                return $c;
            }, $item['children'] ?? []);

            return $item;
        }, array_values($items));
    }

    public function pageUrl(string $slug): string
    {
        return url('/admin/page/'.$slug);
    }

    protected function isUrl(string $slug): bool
    {
        return str_starts_with($slug, '/') || str_starts_with($slug, 'http://') || str_starts_with($slug, 'https://');
    }
}
