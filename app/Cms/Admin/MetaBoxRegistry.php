<?php

namespace App\Cms\Admin;

use App\Models\Post;

/**
 * Meta boxes on the post editor.
 *
 * Declarative (values saved to post meta automatically):
 *   add_meta_box('product_data', 'Product data', 'product', [
 *       'context' => 'normal',
 *       'fields' => [
 *           ['name' => '_price', 'label' => 'Price', 'type' => 'number', 'attributes' => ['step' => '0.01']],
 *           ['name' => '_sku',   'label' => 'SKU'],
 *       ],
 *   ]);
 *
 * Or HTML (inputs named "meta_box[...]" are posted back; handle them on "save_post"):
 *   add_meta_box('seo', 'SEO', ['post', 'page'], ['callback' => fn (Post $post) => '<input name="meta_box[seo_title]">']);
 */
class MetaBoxRegistry
{
    /** @var array<string, array> */
    protected array $boxes = [];

    public function add(string $id, string $title, string|array $screens, array $args = []): void
    {
        $this->boxes[$id] = array_merge([
            'id' => $id,
            'title' => $title,
            'screens' => (array) $screens,
            'context' => 'normal',   // normal | side
            'priority' => 10,
            'fields' => [],
            'callback' => null,
            'capability' => null,
        ], $args);
    }

    public function remove(string $id): void
    {
        unset($this->boxes[$id]);
    }

    /**
     * @return array<int, array>
     */
    public function forType(string $type): array
    {
        $boxes = array_filter($this->boxes, fn ($b) => in_array($type, $b['screens'], true) || in_array('*', $b['screens'], true));
        $boxes = apply_filters('meta_boxes', $boxes, $type);
        uasort($boxes, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_values(array_filter($boxes, fn ($b) => ! $b['capability'] || current_user_can($b['capability'])));
    }

    /**
     * Data for the editor UI.
     */
    public function resolveFor(string $type, ?Post $post): array
    {
        return array_map(function ($box) use ($post) {
            $values = [];
            foreach ($box['fields'] as $i => $field) {
                $field = array_merge(['name' => null, 'label' => null, 'type' => 'text', 'default' => null, 'choices' => [], 'description' => null, 'attributes' => [], 'placeholder' => null], $field);
                if (is_callable($field['choices'])) {
                    $field['choices'] = call_user_func($field['choices']);
                }
                $box['fields'][$i] = $field;
                if ($field['name']) {
                    $values[$field['name']] = $post ? ($post->getMeta($field['name']) ?? $field['default']) : $field['default'];
                }
            }

            $html = null;
            if ($box['callback']) {
                $html = (string) call_user_func($box['callback'], $post);
            }

            return [
                'id' => $box['id'],
                'title' => $box['title'],
                'context' => $box['context'],
                'fields' => array_map(fn ($f) => array_diff_key($f, ['sanitize' => 1]), $box['fields']),
                'values' => $values,
                'html' => $html,
            ];
        }, $this->forType($type));
    }

    /**
     * Persist declarative meta box fields.
     */
    public function save(Post $post, array $input): void
    {
        foreach ($this->forType($post->type) as $box) {
            foreach ($box['fields'] as $field) {
                $name = $field['name'] ?? null;
                if (! $name || ! array_key_exists($name, $input)) {
                    continue;
                }
                $value = $input[$name];
                if (isset($field['sanitize']) && is_callable($field['sanitize'])) {
                    $value = call_user_func($field['sanitize'], $value);
                } elseif (($field['type'] ?? 'text') === 'checkbox' || ($field['type'] ?? '') === 'toggle') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                }
                $post->setMeta($name, apply_filters("sanitize_post_meta_{$name}", $value, $post));
            }
        }
    }
}
