<?php

namespace App\Cms\Core;

use App\Cms\Admin\AdminMenu;
use App\Cms\Admin\SettingsRegistry;
use App\Cms\Admin\ToolsRegistry;
use App\Cms\Content\PostTypeRegistry;
use App\Cms\Content\TaxonomyRegistry;
use App\Cms\Support\Capabilities;
use App\Cms\Support\Permalinks;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Term;
use DateTimeZone;

/**
 * The default admin: navigation, Settings screens, Tools and Dashboard
 * widgets — all registered through the same public API plugins use.
 */
class CoreAdmin
{
    public function register(): void
    {
        add_action('_core_admin_menu', [$this, 'menu']);

        $this->settingsPages();
        $this->tools();
        $this->dashboardWidgets();
    }

    /* ---------------------------------------------------------------------
     | Navigation
     * ------------------------------------------------------------------- */

    public function menu(AdminMenu $menu): void
    {
        $menu->addMenuPage('Dashboard', 'Dashboard', 'read', 'dashboard', null, 'layout-dashboard', 2, [
            'url' => url('/admin'),
        ]);

        // Post types (Posts, Pages and every custom type a plugin registers).
        $this->postTypeMenus($menu);

        $menu->addMenuPage('Comments', 'Comments', 'moderate_comments', 'comments', null, 'message-square', 8, [
            'url' => url('/admin/comments'),
            'badge' => $this->pendingComments(),
        ]);

        $menu->addMenuPage('Media', 'Media', 'upload_files', 'media', null, 'image', 10, [
            'url' => url('/admin/media'),
        ]);

        // Appearance
        $menu->addMenuPage('Appearance', 'Appearance', 'edit_theme_options', 'appearance', null, 'paintbrush', 60, ['separator_before' => true]);
        $menu->addSubmenuPage('appearance', 'Themes', 'Themes', 'switch_themes', 'themes', null, 10, ['url' => url('/admin/themes')]);
        $menu->addSubmenuPage('appearance', 'Customize', 'Customize', 'edit_theme_options', 'customize', null, 20, ['url' => url('/admin/customize')]);
        if (! config('cms.disallow_file_edit')) {
            $menu->addSubmenuPage('appearance', 'Theme File Editor', 'Theme File Editor', 'edit_themes', 'theme-editor', null, 90, ['url' => url('/admin/theme-editor')]);
        }

        // Plugins
        $menu->addMenuPage('Plugins', 'Plugins', 'activate_plugins', 'plugins', null, 'plug', 65);
        $menu->addSubmenuPage('plugins', 'Installed Plugins', 'Installed Plugins', 'activate_plugins', 'installed-plugins', null, 10, ['url' => url('/admin/plugins')]);
        if (! config('cms.disallow_file_mods')) {
            $menu->addSubmenuPage('plugins', 'Add Plugin', 'Add Plugin', 'install_plugins', 'plugin-install', null, 20, ['url' => url('/admin/plugins/add')]);
        }
        if (! config('cms.disallow_file_edit')) {
            $menu->addSubmenuPage('plugins', 'Plugin File Editor', 'Plugin File Editor', 'edit_plugins', 'plugin-editor', null, 90, ['url' => url('/admin/plugin-editor')]);
        }

        // Users (subscribers still see their Profile)
        $menu->addMenuPage('Users', 'Users', 'read', 'users', null, 'users', 70);
        $menu->addSubmenuPage('users', 'All Users', 'All Users', 'list_users', 'all-users', null, 10, ['url' => url('/admin/users')]);
        $menu->addSubmenuPage('users', 'Add User', 'Add User', 'create_users', 'user-new', null, 20, ['url' => url('/admin/users/create')]);
        $menu->addSubmenuPage('users', 'Roles', 'Roles', 'manage_roles', 'roles', null, 30, ['url' => url('/admin/roles')]);
        $menu->addSubmenuPage('users', 'Profile', 'Profile', 'read', 'profile', null, 40, ['url' => url('/admin/profile')]);

        // Tools
        $menu->addMenuPage('Tools', 'Tools', 'edit_posts', 'tools', null, 'wrench', 75);
        $menu->addSubmenuPage('tools', 'Available Tools', 'Available Tools', 'edit_posts', 'available-tools', null, 10, ['url' => url('/admin/tools')]);

        foreach (app(ToolsRegistry::class)->all() as $slug => $tool) {
            if ($tool['callback']) {
                $menu->addPage($slug, $tool['title'], $tool['capability'], $tool['callback'], 'tools');
            }
        }

        // Settings — every registered settings page (core and plugins).
        $menu->addMenuPage('Settings', 'Settings', 'manage_options', 'settings', null, 'settings', 80);
        foreach (app(SettingsRegistry::class)->all() as $slug => $page) {
            if ($page['parent'] === null) {
                continue;
            }
            $menu->addSubmenuPage($page['parent'], $page['title'], $page['menu_title'], $page['capability'], 'settings-'.$slug, null, $page['position'], [
                'url' => url('/admin/settings/'.$slug),
            ]);
        }
    }

    protected function postTypeMenus(AdminMenu $menu): void
    {
        $taxonomies = app(TaxonomyRegistry::class);
        $offset = 0;

        foreach (app(PostTypeRegistry::class)->all() as $name => $type) {
            if (! $type['show_ui'] || ! $type['show_in_menu']) {
                continue;
            }

            $slug = match ($name) {
                'post' => 'posts',
                'page' => 'pages',
                default => 'edit-'.$name,
            };

            $all = ['url' => url('/admin/content/'.$name)];
            $new = ['url' => url('/admin/content/'.$name.'/create')];

            // show_in_menu => 'parent-slug' nests the type under another menu.
            if (is_string($type['show_in_menu'])) {
                $menu->addSubmenuPage($type['show_in_menu'], $type['labels']['all_items'], $type['labels']['menu_name'], $type['capabilities']['edit'], $slug, null, null, $all);

                continue;
            }

            $menu->addMenuPage(
                $type['label'],
                $type['labels']['menu_name'],
                $type['capabilities']['edit'],
                $slug,
                null,
                $type['menu_icon'],
                $type['menu_position'] ?? (25 + ($offset++) / 10),
                $all
            );

            $menu->addSubmenuPage($slug, $type['labels']['all_items'], $type['labels']['all_items'], $type['capabilities']['edit'], $slug.'-all', null, 10, $all);
            $menu->addSubmenuPage($slug, $type['labels']['add_new_item'], $type['labels']['add_new'], $type['capabilities']['edit'], $slug.'-new', null, 20, $new);

            $pos = 30;
            foreach ($taxonomies->forType($name) as $taxName => $tax) {
                if (! $tax['show_ui'] || ! $tax['show_in_menu']) {
                    continue;
                }
                $menu->addSubmenuPage($slug, $tax['label'], $tax['labels']['menu_name'], $tax['capability'], $slug.'-tax-'.$taxName, null, $pos++, [
                    'url' => url('/admin/terms/'.$taxName.'?post_type='.$name),
                ]);
            }
        }
    }

    protected function pendingComments(): ?int
    {
        try {
            $count = Comment::where('status', 'pending')->count();

            return $count ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /* ---------------------------------------------------------------------
     | Settings screens
     * ------------------------------------------------------------------- */

    protected function settingsPages(): void
    {
        $pages = fn () => ['0' => '— Select —'] + Post::where('type', 'page')->whereIn('status', ['publish', 'private', 'draft'])->orderBy('title')->pluck('title', 'id')->all();

        register_settings_page('general', [
            'title' => 'General Settings', 'menu_title' => 'General', 'position' => 10,
            'sections' => [[
                'id' => 'site',
                'fields' => [
                    ['name' => 'blogname', 'label' => 'Site Title', 'default' => config('app.name'), 'rules' => 'required|string|max:190'],
                    ['name' => 'blogdescription', 'label' => 'Tagline', 'description' => 'In a few words, explain what this site is about.'],
                    ['name' => 'site_icon', 'label' => 'Site Icon', 'type' => 'media', 'description' => 'Shown in browser tabs. Should be square and at least 512 × 512 pixels.'],
                    ['name' => 'admin_email', 'label' => 'Administration Email Address', 'type' => 'email', 'rules' => 'required|email', 'description' => 'This address is used for admin purposes.'],
                    ['name' => 'users_can_register', 'label' => 'Membership', 'type' => 'checkbox', 'description' => 'Anyone can register'],
                    ['name' => 'default_role', 'label' => 'New User Default Role', 'type' => 'role', 'default' => 'subscriber'],
                    ['name' => 'site_language', 'label' => 'Site Language', 'type' => 'select', 'default' => 'en', 'choices' => [
                        'en' => 'English', 'en_GB' => 'English (UK)', 'fil' => 'Filipino', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'ja' => '日本語', 'zh_CN' => '简体中文',
                    ]],
                    ['name' => 'timezone_string', 'label' => 'Timezone', 'type' => 'timezone', 'default' => 'UTC',
                        'choices' => fn () => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers())],
                    ['name' => 'date_format', 'label' => 'Date Format', 'type' => 'radio', 'default' => 'F j, Y', 'attributes' => ['allow_custom' => true],
                        'choices' => fn () => $this->formatChoices(['F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'])],
                    ['name' => 'time_format', 'label' => 'Time Format', 'type' => 'radio', 'default' => 'g:i a', 'attributes' => ['allow_custom' => true],
                        'choices' => fn () => $this->formatChoices(['g:i a', 'g:i A', 'H:i'])],
                    ['name' => 'start_of_week', 'label' => 'Week Starts On', 'type' => 'select', 'default' => '1',
                        'choices' => ['0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday']],
                ],
            ]],
        ]);

        register_settings_page('writing', [
            'title' => 'Writing Settings', 'menu_title' => 'Writing', 'position' => 20,
            'sections' => [[
                'id' => 'writing',
                'fields' => [
                    ['name' => 'default_category', 'label' => 'Default Post Category', 'type' => 'category', 'default' => 1,
                        'choices' => fn () => Term::where('taxonomy', 'category')->orderBy('name')->pluck('name', 'id')->all()],
                    ['name' => 'default_post_status', 'label' => 'Default Post Status', 'type' => 'select', 'default' => 'draft',
                        'choices' => ['draft' => 'Draft', 'pending' => 'Pending Review', 'publish' => 'Published']],
                    ['name' => 'default_editor_mode', 'label' => 'Default Editor', 'type' => 'radio', 'default' => 'visual',
                        'choices' => ['visual' => 'Visual (rich text)', 'html' => 'Code (HTML)']],
                    ['name' => 'convert_line_breaks', 'label' => 'Formatting', 'type' => 'checkbox', 'default' => false,
                        'description' => 'Convert line breaks in Code-mode content to paragraphs automatically'],
                ],
            ]],
        ]);

        register_settings_page('reading', [
            'title' => 'Reading Settings', 'menu_title' => 'Reading', 'position' => 30,
            'sections' => [[
                'id' => 'reading',
                'fields' => [
                    ['name' => 'show_on_front', 'label' => 'Your homepage displays', 'type' => 'radio', 'default' => 'posts',
                        'choices' => ['posts' => 'Your latest posts', 'page' => 'A static page (select below)']],
                    ['name' => 'page_on_front', 'label' => 'Homepage', 'type' => 'page', 'default' => 0, 'choices' => $pages,
                        'depends_on' => ['field' => 'show_on_front', 'value' => 'page']],
                    ['name' => 'page_for_posts', 'label' => 'Posts page', 'type' => 'page', 'default' => 0, 'choices' => $pages,
                        'depends_on' => ['field' => 'show_on_front', 'value' => 'page']],
                    ['name' => 'posts_per_page', 'label' => 'Blog pages show at most', 'type' => 'number', 'default' => 10,
                        'rules' => 'required|integer|min:1|max:100', 'attributes' => ['min' => 1, 'suffix' => 'posts'], 'width' => 'small'],
                    ['name' => 'excerpt_on_archives', 'label' => 'For each post in a feed, include', 'type' => 'radio', 'default' => 'excerpt',
                        'choices' => ['full' => 'Full text', 'excerpt' => 'Excerpt']],
                    ['name' => 'discourage_search_engines', 'label' => 'Search engine visibility', 'type' => 'checkbox', 'default' => false,
                        'description' => 'Discourage search engines from indexing this site'],
                ],
            ]],
        ]);

        register_settings_page('discussion', [
            'title' => 'Discussion Settings', 'menu_title' => 'Discussion', 'position' => 40,
            'sections' => [
                ['id' => 'defaults', 'title' => 'Default post settings', 'fields' => [
                    ['name' => 'default_comment_status', 'label' => 'Comments', 'type' => 'checkbox', 'default' => true,
                        'description' => 'Allow people to submit comments on new posts'],
                ]],
                ['id' => 'other', 'title' => 'Other comment settings', 'fields' => [
                    ['name' => 'require_name_email', 'label' => 'Name and email', 'type' => 'checkbox', 'default' => true, 'description' => 'Comment author must fill out name and email'],
                    ['name' => 'comment_registration', 'label' => 'Registration', 'type' => 'checkbox', 'default' => false, 'description' => 'Users must be registered and logged in to comment'],
                    ['name' => 'close_comments_for_old_posts', 'label' => 'Close old comments', 'type' => 'checkbox', 'default' => false, 'description' => 'Automatically close comments on old posts'],
                    ['name' => 'close_comments_days_old', 'label' => 'Days before closing', 'type' => 'number', 'default' => 14, 'width' => 'small',
                        'depends_on' => ['field' => 'close_comments_for_old_posts', 'value' => true]],
                    ['name' => 'thread_comments', 'label' => 'Threaded comments', 'type' => 'checkbox', 'default' => true, 'description' => 'Enable threaded (nested) comments'],
                    ['name' => 'thread_comments_depth', 'label' => 'Levels deep', 'type' => 'number', 'default' => 5, 'width' => 'small',
                        'depends_on' => ['field' => 'thread_comments', 'value' => true]],
                ]],
                ['id' => 'moderation', 'title' => 'Moderation', 'fields' => [
                    ['name' => 'comment_moderation', 'label' => 'Before a comment appears', 'type' => 'checkbox', 'default' => false, 'description' => 'Comment must be manually approved'],
                    ['name' => 'comment_previously_approved', 'label' => '', 'type' => 'checkbox', 'default' => true, 'description' => 'Comment author must have a previously approved comment'],
                    ['name' => 'comment_max_links', 'label' => 'Hold if it contains at least', 'type' => 'number', 'default' => 2, 'width' => 'small', 'attributes' => ['suffix' => 'links']],
                    ['name' => 'moderation_keys', 'label' => 'Comment Moderation', 'type' => 'textarea',
                        'description' => 'When a comment contains any of these words (one per line), it will be held in the moderation queue.'],
                    ['name' => 'disallowed_keys', 'label' => 'Disallowed Comment Keys', 'type' => 'textarea',
                        'description' => 'When a comment contains any of these words (one per line), it will be put in the Trash.'],
                ]],
                ['id' => 'avatars', 'title' => 'Avatars', 'fields' => [
                    ['name' => 'show_avatars', 'label' => 'Avatar Display', 'type' => 'checkbox', 'default' => true, 'description' => 'Show Avatars'],
                ]],
            ],
        ]);

        register_settings_page('media', [
            'title' => 'Media Settings', 'menu_title' => 'Media', 'position' => 50,
            'sections' => [
                ['id' => 'sizes', 'title' => 'Image sizes', 'description' => 'The sizes listed below determine the maximum dimensions in pixels to use when adding an image to the Media Library.', 'fields' => [
                    ['name' => 'thumbnail_size_w', 'label' => 'Thumbnail width', 'type' => 'number', 'default' => 150, 'width' => 'small'],
                    ['name' => 'thumbnail_size_h', 'label' => 'Thumbnail height', 'type' => 'number', 'default' => 150, 'width' => 'small'],
                    ['name' => 'thumbnail_crop', 'label' => '', 'type' => 'checkbox', 'default' => true, 'description' => 'Crop thumbnail to exact dimensions'],
                    ['name' => 'medium_size_w', 'label' => 'Medium max width', 'type' => 'number', 'default' => 300, 'width' => 'small'],
                    ['name' => 'medium_size_h', 'label' => 'Medium max height', 'type' => 'number', 'default' => 300, 'width' => 'small'],
                    ['name' => 'large_size_w', 'label' => 'Large max width', 'type' => 'number', 'default' => 1024, 'width' => 'small'],
                    ['name' => 'large_size_h', 'label' => 'Large max height', 'type' => 'number', 'default' => 1024, 'width' => 'small'],
                ]],
                ['id' => 'uploads', 'title' => 'Uploading Files', 'fields' => [
                    ['name' => 'uploads_use_yearmonth_folders', 'label' => '', 'type' => 'checkbox', 'default' => true, 'description' => 'Organize my uploads into month- and year-based folders'],
                ]],
            ],
        ]);

        register_settings_page('permalinks', [
            'title' => 'Permalink Settings', 'menu_title' => 'Permalinks', 'position' => 60,
            'description' => 'Choose a custom URL structure for your posts. Pages always use their (hierarchical) slug.',
            'sections' => [
                ['id' => 'common', 'title' => 'Permalink structure', 'fields' => [
                    ['name' => 'permalink_structure', 'label' => 'Structure', 'type' => 'permalink', 'default' => '/%postname%',
                        'choices' => Permalinks::STRUCTURES,
                        'sanitize' => function ($value) {
                            $value = trim((string) $value);
                            if ($value === '') {
                                return '';
                            }
                            $value = '/'.trim(preg_replace('#[^a-z0-9_%/\-]#i', '', $value), '/');

                            // A structure must contain something unique per post.
                            return preg_match('/%(postname|post_id)%/', $value) ? $value : rtrim($value, '/').'/%postname%';
                        }],
                ]],
                ['id' => 'optional', 'title' => 'Optional', 'description' => 'Custom structures for category and tag URLs. Leave blank for the defaults.', 'fields' => [
                    ['name' => 'category_base', 'label' => 'Category base', 'placeholder' => 'category', 'sanitize' => fn ($v) => trim(preg_replace('#[^a-z0-9\-/]#i', '', (string) $v), '/')],
                    ['name' => 'tag_base', 'label' => 'Tag base', 'placeholder' => 'tag', 'sanitize' => fn ($v) => trim(preg_replace('#[^a-z0-9\-/]#i', '', (string) $v), '/')],
                ]],
            ],
        ]);

        register_settings_page('privacy', [
            'title' => 'Privacy', 'menu_title' => 'Privacy', 'position' => 70,
            'description' => 'As a website owner, you may need to follow national or international privacy laws. Create and publish a Privacy Policy page and select it below.',
            'sections' => [[
                'id' => 'policy', 'title' => 'Privacy Policy page', 'fields' => [
                    ['name' => 'privacy_policy_page', 'label' => 'Privacy Policy page', 'type' => 'page', 'default' => 0, 'choices' => $pages],
                    ['name' => 'cookie_notice', 'label' => 'Cookie notice', 'type' => 'checkbox', 'default' => false, 'description' => 'Show a cookie consent notice on the front end (themes read this setting)'],
                    ['name' => 'cookie_notice_text', 'label' => 'Notice text', 'type' => 'textarea', 'default' => 'We use cookies to improve your experience on our site.',
                        'depends_on' => ['field' => 'cookie_notice', 'value' => true]],
                ],
            ]],
        ]);
    }

    protected function formatChoices(array $formats): array
    {
        $out = [];
        foreach ($formats as $f) {
            $out[$f] = now()->format($f).'  ('.$f.')';
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     | Tools & dashboard
     * ------------------------------------------------------------------- */

    protected function tools(): void
    {
        register_tool('export', [
            'title' => 'Export', 'icon' => 'download', 'capability' => 'export',
            'description' => 'Download your posts, pages, custom post types, terms, comments and settings as a JSON file.',
            'url' => url('/admin/tools/export'), 'action_label' => 'Export content',
        ]);
        register_tool('import', [
            'title' => 'Import', 'icon' => 'upload', 'capability' => 'import',
            'description' => 'Import content from a BoholwebCMS JSON export file.',
            'url' => url('/admin/tools/import'), 'action_label' => 'Import content',
        ]);
        register_tool('site-health', [
            'title' => 'Site Health', 'icon' => 'heart-pulse', 'capability' => 'manage_options',
            'description' => 'Information about your server, PHP, database, active plugins and theme.',
            'url' => url('/admin/tools/site-health'), 'action_label' => 'View report',
        ]);
        register_tool('hooks-inspector', [
            'title' => 'Hooks Inspector', 'icon' => 'webhook', 'capability' => 'manage_options',
            'description' => 'See every action, filter and shortcode registered on this request — handy when building plugins.',
            'url' => url('/admin/tools/hooks'), 'action_label' => 'Inspect hooks',
        ]);
        register_tool('clear-cache', [
            'title' => 'Clear Cache', 'icon' => 'eraser', 'capability' => 'manage_options',
            'description' => 'Clear the application, view, route and config caches.',
            'url' => url('/admin/tools/clear-cache'), 'action_label' => 'Clear caches',
        ]);
    }

    protected function dashboardWidgets(): void
    {
        add_dashboard_widget('welcome', 'Welcome', null, ['component' => 'core/welcome', 'priority' => 1, 'capability' => 'edit_theme_options']);
        add_dashboard_widget('at_a_glance', 'At a Glance', null, ['component' => 'core/at-a-glance', 'priority' => 5, 'props' => fn () => $this->glance()]);
        add_dashboard_widget('activity', 'Activity', null, ['component' => 'core/activity', 'priority' => 10, 'props' => fn () => $this->activity()]);
        add_dashboard_widget('quick_draft', 'Quick Draft', null, [
            'component' => 'core/quick-draft', 'context' => 'side', 'priority' => 5, 'capability' => 'edit_posts',
            'props' => fn () => ['drafts' => Post::where('type', 'post')->where('status', 'draft')->where('author_id', current_user_id())
                ->latest('updated_at')->limit(3)->get(['id', 'title', 'updated_at'])->map(fn ($p) => [
                    'id' => $p->id, 'title' => $p->title ?: '(no title)', 'date' => format_cms_date($p->updated_at), 'edit_url' => url('/admin/content/post/'.$p->id.'/edit'),
                ])],
        ]);
    }

    protected function glance(): array
    {
        $items = [];
        foreach (app(PostTypeRegistry::class)->all() as $name => $type) {
            if (! $type['show_ui']) {
                continue;
            }
            $items[] = [
                'label' => $type['label'],
                'count' => Post::where('type', $name)->where('status', 'publish')->count(),
                'url' => url('/admin/content/'.$name),
                'icon' => $type['menu_icon'],
            ];
        }
        $items[] = ['label' => 'Comments', 'count' => Comment::where('status', 'approved')->count(), 'url' => url('/admin/comments'), 'icon' => 'message-square'];
        $pending = Comment::where('status', 'pending')->count();

        $theme = app(\App\Cms\Extensions\ThemeManager::class)->active();

        return [
            'items' => apply_filters('dashboard_glance_items', $items),
            'pending_comments' => $pending,
            'theme' => $theme['name'] ?? null,
            'version' => cms_version(),
            'search_discouraged' => (bool) get_option('discourage_search_engines'),
        ];
    }

    protected function activity(): array
    {
        $map = fn ($p) => [
            'id' => $p->id,
            'title' => $p->title ?: '(no title)',
            'date' => format_cms_date($p->published_at, 'M j, g:i a'),
            'edit_url' => url('/admin/content/'.$p->type.'/'.$p->id.'/edit'),
        ];

        return [
            'scheduled' => Post::where('status', 'future')->orderBy('published_at')->limit(5)->get()->map($map),
            'recent' => Post::where('status', 'publish')->whereIn('type', ['post', 'page'])->latest('published_at')->limit(5)->get()->map($map),
            'comments' => Comment::with('post:id,title,type')->whereIn('status', ['approved', 'pending'])->latest()->limit(5)->get()->map(fn ($c) => [
                'id' => $c->id,
                'author' => $c->author_name ?? $c->user?->name,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags($c->content), 90),
                'status' => $c->status,
                'post' => $c->post?->title,
                'post_url' => $c->post ? url('/admin/content/'.$c->post->type.'/'.$c->post->id.'/edit') : null,
            ]),
        ];
    }
}
