<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Extensions\ThemeManager;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class ThemeController extends Controller
{
    public function __construct(protected ThemeManager $themes) {}

    public function index()
    {
        $active = $this->themes->activeSlug();

        return Inertia::render('Appearance/Themes', [
            'themes' => collect($this->themes->all(true))->map(fn ($t) => [
                'slug' => $t['slug'],
                'name' => $t['name'],
                'description' => $t['description'],
                'version' => $t['version'],
                'author' => $t['author'],
                'author_uri' => $t['author_uri'],
                'tags' => $t['tags'],
                'parent' => $t['parent'],
                'screenshot' => $t['screenshot'] ? $this->themes->assetUrl($t, $t['screenshot']) : null,
                'active' => $t['slug'] === $active,
                'built' => is_file($t['dir'].'/'.$t['script']),
            ])->values(),
            'can' => [
                'install' => current_user_can('install_themes') && ! config('cms.disallow_file_mods'),
                'delete' => current_user_can('delete_themes') && ! config('cms.disallow_file_mods'),
                'customize' => current_user_can('edit_theme_options'),
            ],
        ]);
    }

    public function activate(string $slug)
    {
        try {
            $this->themes->switch($slug);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'New theme activated. Visit your site to see it.');
    }

    public function upload(Request $request)
    {
        $request->validate(['package' => 'required|file|mimes:zip|max:51200', 'overwrite' => 'nullable|boolean']);

        try {
            $slug = $this->themes->installZip($request->file('package')->getRealPath(), (bool) $request->boolean('overwrite'));
        } catch (Throwable $e) {
            return back()->with('error', 'Installation failed: '.$e->getMessage());
        }

        return back()->with('success', "Theme \"{$slug}\" installed successfully.");
    }

    public function destroy(string $slug)
    {
        try {
            $this->themes->delete($slug);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Theme deleted.');
    }

    public function dismissError()
    {
        update_option('theme_error', null);

        return back();
    }

    /* ---------------------------------------------------------------------
     | Customizer
     * ------------------------------------------------------------------- */

    public function customize()
    {
        $sections = $this->sections();
        $values = [];
        foreach ($sections as $section) {
            foreach ($section['controls'] as $control) {
                $values[$control['id']] = $this->currentValue($control);
            }
        }

        // Resolve media URLs so image controls can show a preview.
        $mediaIds = collect($sections)->flatMap(fn ($s) => $s['controls'])->where('type', 'media')->map(fn ($c) => $values[$c['id']] ?? null)->filter()->all();

        return Inertia::render('Appearance/Customize', [
            'theme' => ['slug' => $this->themes->activeSlug(), 'name' => $this->themes->active()['name'] ?? ''],
            'sections' => $sections,
            'values' => $values,
            'media' => Media::whereIn('id', $mediaIds)->get()->mapWithKeys(fn ($m) => [$m->id => $m->sizeUrl('medium')]),
            'previewUrl' => url('/?customize_preview=1'),
        ]);
    }

    public function saveCustomize(Request $request)
    {
        $values = (array) $request->input('values', []);

        foreach ($this->sections() as $section) {
            foreach ($section['controls'] as $control) {
                if (! array_key_exists($control['id'], $values)) {
                    continue;
                }
                $value = $this->sanitize($control, $values[$control['id']]);
                $value = apply_filters("customize_sanitize_{$control['id']}", $value, $control);

                if ($control['setting_type'] === 'option') {
                    update_option($control['id'], $value);
                } else {
                    $this->themes->setMod($control['id'], $value);
                }
            }
        }

        do_action('customize_save_after', $values);

        return back()->with('success', 'Changes published.');
    }

    protected function sections(): array
    {
        $pages = fn () => ['0' => '— Select —'] + Post::where('type', 'page')->where('status', 'publish')->orderBy('title')->pluck('title', 'id')->all();

        $sections = [
            [
                'id' => 'title_tagline', 'title' => 'Site Identity', 'priority' => 10,
                'controls' => [
                    ['id' => 'custom_logo', 'label' => 'Logo', 'type' => 'media'],
                    ['id' => 'blogname', 'label' => 'Site Title', 'type' => 'text', 'setting_type' => 'option'],
                    ['id' => 'blogdescription', 'label' => 'Tagline', 'type' => 'text', 'setting_type' => 'option'],
                    ['id' => 'site_icon', 'label' => 'Site Icon', 'type' => 'media', 'setting_type' => 'option', 'description' => 'Square image, at least 512 × 512 px.'],
                    ['id' => 'display_header_text', 'label' => 'Display Site Title and Tagline', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            [
                'id' => 'static_front_page', 'title' => 'Homepage Settings', 'priority' => 120,
                'description' => 'You can choose what’s displayed on the homepage of your site.',
                'controls' => [
                    ['id' => 'show_on_front', 'label' => 'Your homepage displays', 'type' => 'radio', 'setting_type' => 'option',
                        'choices' => ['posts' => 'Your latest posts', 'page' => 'A static page'], 'refresh' => true],
                    ['id' => 'page_on_front', 'label' => 'Homepage', 'type' => 'select', 'setting_type' => 'option', 'choices' => $pages, 'refresh' => true],
                    ['id' => 'page_for_posts', 'label' => 'Posts page', 'type' => 'select', 'setting_type' => 'option', 'choices' => $pages, 'refresh' => true],
                ],
            ],
            [
                'id' => 'custom_css', 'title' => 'Additional CSS', 'priority' => 200,
                'description' => 'Add your own CSS code here to customize the appearance and layout of your site.',
                'controls' => [
                    ['id' => 'custom_css', 'label' => 'CSS', 'type' => 'code', 'attributes' => ['language' => 'css']],
                ],
            ],
        ];

        // Theme-defined sections (theme.json "customizer"), parent first.
        foreach ($this->themes->stack() as $theme) {
            foreach ($theme['customizer'] as $i => $section) {
                $section['priority'] ??= 50 + $i;
                $sections[] = $section;
            }
        }

        $sections = apply_filters('customize_register', $sections);

        return collect($sections)
            ->map(function ($section) {
                $section['controls'] = array_map(function ($c) {
                    $c = array_merge(['type' => 'text', 'setting_type' => 'theme_mod', 'default' => null, 'choices' => [], 'description' => null, 'refresh' => false, 'attributes' => []], $c);
                    if (is_callable($c['choices'])) {
                        $c['choices'] = call_user_func($c['choices']);
                    }

                    return $c;
                }, $section['controls'] ?? []);
                $section['priority'] ??= 100;

                return $section;
            })
            ->sortBy('priority')->values()->all();
    }

    protected function currentValue(array $control): mixed
    {
        if ($control['setting_type'] === 'option') {
            return get_option($control['id'], $control['default']);
        }

        return $this->themes->mods()[$control['id']] ?? $control['default'];
    }

    protected function sanitize(array $control, mixed $value): mixed
    {
        return match ($control['type']) {
            'checkbox', 'toggle' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number', 'range' => is_numeric($value) ? $value + 0 : null,
            'color' => preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', (string) $value) ? $value : null,
            'media' => $value ? (int) $value : null,
            'url' => esc_url((string) $value) !== '' ? (string) $value : '',
            'select', 'radio' => array_key_exists((string) $value, $control['choices']) ? $value : $control['default'],
            'code' => str_replace('</style', '', (string) $value),
            'textarea' => (string) $value,
            default => is_string($value) ? sanitize_text_field($value) : $value,
        };
    }
}
