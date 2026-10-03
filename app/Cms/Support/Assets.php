<?php

namespace App\Cms\Support;

/**
 * Script & style queue for the front end ("front") and the admin ("admin").
 *
 *   add_action('enqueue_scripts', function () {
 *       enqueue_style('shop', plugin_url(__FILE__, 'assets/shop.css'));
 *       enqueue_script('shop', plugin_url(__FILE__, 'assets/shop.js'), [], '1.0');
 *       localize_script('shop', 'ShopConfig', ['ajaxUrl' => url('/cms-ajax')]);
 *   });
 *
 *   add_action('admin_enqueue_scripts', function () {
 *       enqueue_script('shop-admin', plugin_url(__FILE__, 'assets/admin.js'));
 *   });
 *
 * Scripts load after the CMS runtime, so `window.CMS` (React, Inertia helpers,
 * JS hooks, component registry) is available to them.
 */
class Assets
{
    protected array $scripts = ['front' => [], 'admin' => []];

    protected array $styles = ['front' => [], 'admin' => []];

    protected array $inline = ['front' => [], 'admin' => []];

    protected string $context = 'front';

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function context(): string
    {
        return $this->context;
    }

    public function enqueueScript(string $handle, string $src, array $deps = [], ?string $version = null, bool $inFooter = true, array $attributes = []): void
    {
        $this->scripts[$this->context][$handle] = compact('handle', 'src', 'deps', 'version', 'inFooter', 'attributes') + ['data' => []];
    }

    public function enqueueStyle(string $handle, string $src, array $deps = [], ?string $version = null, string $media = 'all'): void
    {
        $this->styles[$this->context][$handle] = compact('handle', 'src', 'deps', 'version', 'media');
    }

    public function dequeueScript(string $handle): void
    {
        unset($this->scripts[$this->context][$handle]);
    }

    public function dequeueStyle(string $handle): void
    {
        unset($this->styles[$this->context][$handle]);
    }

    public function isEnqueued(string $handle, string $type = 'script'): bool
    {
        return $type === 'script'
            ? isset($this->scripts[$this->context][$handle])
            : isset($this->styles[$this->context][$handle]);
    }

    /**
     * Expose a JS object before a script runs (like wp_localize_script).
     */
    public function localizeScript(string $handle, string $objectName, array $data): void
    {
        if (isset($this->scripts[$this->context][$handle])) {
            $this->scripts[$this->context][$handle]['data'][$objectName] = $data;
        } else {
            $this->addInline('head', 'window['.json_encode($objectName).'] = '.json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES).';');
        }
    }

    public function addInline(string $position, string $code, string $type = 'script'): void
    {
        $this->inline[$this->context][] = compact('position', 'code', 'type');
    }

    public function renderHead(): string
    {
        $html = '';

        foreach ($this->sorted($this->styles[$this->context]) as $style) {
            $html .= sprintf(
                '<link rel="stylesheet" id="%s-css" href="%s" media="%s">'."\n",
                e($style['handle']),
                e($this->versioned($style['src'], $style['version'])),
                e($style['media'])
            );
        }

        foreach ($this->inline[$this->context] as $item) {
            if ($item['position'] === 'head') {
                $html .= $item['type'] === 'style'
                    ? "<style>{$item['code']}</style>\n"
                    : "<script>{$item['code']}</script>\n";
            }
        }

        foreach ($this->sorted($this->scripts[$this->context]) as $script) {
            if (! $script['inFooter']) {
                $html .= $this->scriptTag($script);
            }
        }

        return $html;
    }

    public function renderFooter(): string
    {
        $html = '';

        foreach ($this->sorted($this->scripts[$this->context]) as $script) {
            if ($script['inFooter']) {
                $html .= $this->scriptTag($script);
            }
        }

        foreach ($this->inline[$this->context] as $item) {
            if ($item['position'] === 'footer') {
                $html .= $item['type'] === 'style'
                    ? "<style>{$item['code']}</style>\n"
                    : "<script>{$item['code']}</script>\n";
            }
        }

        return $html;
    }

    protected function scriptTag(array $script): string
    {
        $html = '';
        foreach ($script['data'] as $name => $data) {
            $html .= '<script>window['.json_encode($name).'] = '.json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES).";</script>\n";
        }

        // type="module" keeps execution ordered after the Vite runtime entry.
        $attrs = array_merge(['type' => 'module'], $script['attributes']);
        $attrString = '';
        foreach ($attrs as $k => $v) {
            $attrString .= $v === true ? ' '.e($k) : ' '.e($k).'="'.e($v).'"';
        }

        return $html.sprintf(
            '<script id="%s-js" src="%s"%s></script>'."\n",
            e($script['handle']),
            e($this->versioned($script['src'], $script['version'])),
            $attrString
        );
    }

    protected function versioned(string $src, ?string $version): string
    {
        if (! $version) {
            return $src;
        }

        return $src.(str_contains($src, '?') ? '&' : '?').'ver='.urlencode($version);
    }

    /**
     * Topological sort by deps.
     */
    protected function sorted(array $items): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function ($handle) use (&$visit, &$sorted, &$visiting, $items) {
            if (isset($sorted[$handle]) || ! isset($items[$handle]) || isset($visiting[$handle])) {
                return;
            }
            $visiting[$handle] = true;
            foreach ($items[$handle]['deps'] as $dep) {
                $visit($dep);
            }
            $sorted[$handle] = $items[$handle];
        };

        foreach (array_keys($items) as $handle) {
            $visit($handle);
        }

        return $sorted;
    }
}
