<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Admin\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Renders and saves every settings page registered with register_settings_page()
 * — core (General, Writing, Reading, ...) and plugin pages alike.
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingsRegistry $settings) {}

    public function show(string $page)
    {
        $definition = $this->settings->resolve($page) ?? abort(404);
        $this->authorizeCap($definition['capability']);

        return Inertia::render('Settings/Page', [
            'page' => $definition,
            'roles' => Role::orderBy('id')->pluck('name', 'slug'),
            'mediaPreviews' => $this->mediaPreviews($definition),
        ]);
    }

    public function update(Request $request, string $page)
    {
        $definition = $this->settings->resolve($page) ?? abort(404);
        $this->authorizeCap($definition['capability']);

        $fields = $this->settings->fields($page);
        $input = (array) $request->input('values', []);

        $rules = [];
        foreach ($fields as $name => $field) {
            if ($field['rules']) {
                $rules[$name] = $field['rules'];
            }
        }
        $validator = Validator::make($input, $rules, [], collect($fields)->mapWithKeys(fn ($f, $n) => [$n => $f['label'] ?: $n])->all());
        if ($validator->fails()) {
            return back()->withErrors(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["values.{$k}" => $m[0]])->all())->withInput();
        }

        do_action("pre_update_settings_{$page}", $input);

        foreach ($fields as $name => $field) {
            if (! array_key_exists($name, $input) && ! in_array($field['type'], ['checkbox', 'toggle'], true)) {
                continue;
            }
            $value = $this->cast($field, $input[$name] ?? null);
            if (is_callable($field['sanitize'])) {
                $value = call_user_func($field['sanitize'], $value);
            }
            $value = apply_filters("sanitize_option_{$name}", $value, $name);
            update_option($name, $value);
        }

        do_action("update_settings_{$page}", $input);
        do_action('settings_updated', $page, $input);

        return back()->with('success', 'Settings saved.');
    }

    protected function cast(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'checkbox', 'toggle' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : ($field['default'] ?? 0),
            'page', 'category', 'media' => (int) $value,
            'email' => is_string($value) ? trim($value) : '',
            'url' => is_string($value) ? trim($value) : '',
            'textarea', 'code' => (string) $value,
            'select', 'radio', 'role', 'timezone' => is_array($value) ? null : (string) $value,
            'multiselect', 'checkboxes' => array_values((array) $value),
            default => is_string($value) ? sanitize_text_field($value) : $value,
        };
    }

    protected function mediaPreviews(array $definition): array
    {
        $ids = [];
        foreach ($definition['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'media' && ($v = $definition['values'][$field['name']] ?? null)) {
                    $ids[] = (int) $v;
                }
            }
        }

        return \App\Models\Media::whereIn('id', $ids)->get()->mapWithKeys(fn ($m) => [$m->id => $m->sizeUrl('thumbnail')])->all();
    }
}
