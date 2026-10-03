<?php

namespace App\Cms\Admin;

/**
 *   add_dashboard_widget('sales', 'Sales this week', fn () => '<p>$1,200</p>', ['context' => 'side']);
 *   add_dashboard_widget('my_react', 'Chart', null, ['component' => 'my-plugin/SalesChart', 'props' => [...]]);
 *   remove_dashboard_widget('quick_draft');
 */
class DashboardWidgets
{
    protected array $widgets = [];

    protected array $removed = [];

    public function add(string $id, string $title, ?callable $callback = null, array $args = []): void
    {
        $this->widgets[$id] = array_merge([
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
            'component' => null,     // built-in: core/at-a-glance, core/activity, core/quick-draft, core/welcome
            'props' => [],
            'context' => 'normal',   // normal | side
            'priority' => 10,
            'capability' => 'read',
        ], $args);
    }

    public function remove(string $id): void
    {
        $this->removed[$id] = true;
    }

    public function resolve(): array
    {
        do_action('dashboard_setup', $this);

        $widgets = array_filter(
            $this->widgets,
            fn ($w, $id) => ! isset($this->removed[$id]) && current_user_can($w['capability']),
            ARRAY_FILTER_USE_BOTH
        );

        $widgets = apply_filters('dashboard_widgets', $widgets);
        uasort($widgets, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_values(array_map(function ($w) {
            $props = is_callable($w['props']) ? call_user_func($w['props']) : $w['props'];

            return [
                'id' => $w['id'],
                'title' => $w['title'],
                'context' => $w['context'],
                'component' => $w['component'],
                'props' => $props,
                'html' => $w['callback'] ? (string) call_user_func($w['callback']) : null,
            ];
        }, $widgets));
    }
}
