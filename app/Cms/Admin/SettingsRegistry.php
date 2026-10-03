<?php

namespace App\Cms\Admin;

/**
 * Declarative settings pages. All core Settings screens (General, Writing,
 * Reading, Discussion, Media, Permalinks, Privacy) are built with this, and
 * plugins use the very same API:
 *
 *   register_settings_page('shop', [
 *       'title' => 'Shop Settings',
 *       'menu_title' => 'Shop',          // shown under Settings
 *       'sections' => [[
 *           'id' => 'general', 'title' => 'Store',
 *           'fields' => [
 *               ['name' => 'shop_currency', 'label' => 'Currency', 'type' => 'select',
 *                'choices' => ['USD' => 'US Dollar', 'PHP' => 'Philippine Peso'], 'default' => 'USD'],
 *           ],
 *       ]],
 *   ]);
 *
 *   // Add a field to an existing page/section:
 *   add_settings_field('general', 'site', ['name' => 'company_phone', 'label' => 'Phone']);
 *
 * Field types: text, email, url, password, number, textarea, code, select,
 * radio, checkbox, toggle, color, date, page, category, role, timezone,
 * media, permalink, html (read-only markup).
 */
class SettingsRegistry
{
    /** @var array<string, array> */
    protected array $pages = [];

    public function register(string $slug, array $args): void
    {
        $existing = $this->pages[$slug] ?? [];

        $this->pages[$slug] = array_merge([
            'slug' => $slug,
            'title' => ucfirst($slug).' Settings',
            'menu_title' => ucfirst($slug),
            'description' => null,
            'capability' => 'manage_options',
            'parent' => 'settings',     // menu parent; null to hide from the menu
            'position' => null,
            'sections' => [],
        ], $existing, $args);

        $sections = [];
        foreach ($this->pages[$slug]['sections'] as $section) {
            $section = $this->normalizeSection($section);
            $sections[$section['id']] = $section;
        }
        $this->pages[$slug]['sections'] = $sections;
    }

    public function addSection(string $page, array $section): void
    {
        if (! isset($this->pages[$page])) {
            $this->register($page, []);
        }
        $section = $this->normalizeSection($section);
        $this->pages[$page]['sections'][$section['id']] = $section;
    }

    public function addField(string $page, string $sectionId, array $field): void
    {
        if (! isset($this->pages[$page]['sections'][$sectionId])) {
            $this->addSection($page, ['id' => $sectionId, 'title' => ucfirst($sectionId)]);
        }

        $this->pages[$page]['sections'][$sectionId]['fields'][] = $this->normalizeField($field);
    }

    public function removeField(string $page, string $name): void
    {
        foreach ($this->pages[$page]['sections'] ?? [] as $id => $section) {
            $this->pages[$page]['sections'][$id]['fields'] = array_values(array_filter(
                $section['fields'],
                fn ($f) => $f['name'] !== $name
            ));
        }
    }

    public function has(string $slug): bool
    {
        return isset($this->pages[$slug]);
    }

    public function all(): array
    {
        return $this->pages;
    }

    /**
     * The page definition (without callbacks) plus current values, ready for React.
     */
    public function resolve(string $slug): ?array
    {
        $page = $this->pages[$slug] ?? null;
        if (! $page) {
            return null;
        }

        $page = apply_filters("settings_page_{$slug}", $page);

        $values = [];
        $sections = [];
        foreach ($page['sections'] as $section) {
            $section['fields'] = array_map(function ($field) use (&$values) {
                if (is_callable($field['choices'])) {
                    $field['choices'] = call_user_func($field['choices']);
                }
                if ($field['type'] === 'html' && is_callable($field['html'] ?? null)) {
                    $field['html'] = call_user_func($field['html']);
                }
                if ($field['name']) {
                    $values[$field['name']] = get_option($field['name'], $field['default']);
                }
                unset($field['sanitize'], $field['rules']);

                return $field;
            }, $section['fields']);
            $sections[] = $section;
        }

        $page['sections'] = $sections;
        $page['values'] = $values;

        return $page;
    }

    /**
     * Flat list of all fields on a page (with callbacks), for saving.
     *
     * @return array<string, array>
     */
    public function fields(string $slug): array
    {
        $page = $this->pages[$slug] ?? null;
        if (! $page) {
            return [];
        }
        $page = apply_filters("settings_page_{$slug}", $page);

        $out = [];
        foreach ($page['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['name'] && $field['type'] !== 'html') {
                    $out[$field['name']] = $field;
                }
            }
        }

        return $out;
    }

    protected function normalizeSection(array $section): array
    {
        $section = array_merge([
            'id' => 'default',
            'title' => null,
            'description' => null,
            'fields' => [],
        ], $section);

        $section['fields'] = array_map(fn ($f) => $this->normalizeField($f), $section['fields']);

        return $section;
    }

    protected function normalizeField(array $field): array
    {
        return array_merge([
            'name' => null,
            'label' => null,
            'type' => 'text',
            'default' => null,
            'description' => null,
            'placeholder' => null,
            'choices' => [],
            'attributes' => [],
            'rules' => null,       // Laravel validation rules, e.g. 'nullable|email'
            'sanitize' => null,    // callable(mixed $value): mixed
            'width' => null,       // 'small' | 'regular' | 'large'
            'depends_on' => null,  // ['field' => 'show_on_front', 'value' => 'page']
        ], $field);
    }
}
