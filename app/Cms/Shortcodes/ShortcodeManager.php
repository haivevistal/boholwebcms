<?php

namespace App\Cms\Shortcodes;

/**
 * WordPress-compatible shortcode parser.
 *
 *   add_shortcode('button', function (array $atts, ?string $content, string $tag) {
 *       $atts = shortcode_atts(['url' => '#', 'color' => 'blue'], $atts, $tag);
 *       return '<a class="btn btn-'.e($atts['color']).'" href="'.e($atts['url']).'">'.do_shortcode($content ?? '').'</a>';
 *   });
 *
 * Supports self-closing [tag /], enclosing [tag]...[/tag], quoted & unquoted
 * attributes, positional attributes, nesting via do_shortcode() and escaping
 * with double brackets [[tag]].
 */
class ShortcodeManager
{
    /** @var array<string, callable> */
    protected array $tags = [];

    /** @var callable|null  (hook, value, ...args) => value — wired to apply_filters */
    protected $filter;

    public function __construct(?callable $filter = null)
    {
        $this->filter = $filter;
    }

    public function add(string $tag, callable $callback): void
    {
        $tag = trim($tag);
        if ($tag === '' || preg_match('@[<>&/\[\]\x00-\x20=]@', $tag)) {
            throw new \InvalidArgumentException("Invalid shortcode name: [{$tag}]");
        }
        $this->tags[$tag] = $callback;
    }

    public function remove(string $tag): void
    {
        unset($this->tags[$tag]);
    }

    public function removeAll(): void
    {
        $this->tags = [];
    }

    public function exists(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return array_keys($this->tags);
    }

    /**
     * Whether the content contains the given (or any registered) shortcode.
     */
    public function has(string $content, ?string $tag = null): bool
    {
        if (! str_contains($content, '[')) {
            return false;
        }

        $tags = $tag ? [$tag] : $this->tags();
        if (! $tags) {
            return false;
        }

        return (bool) preg_match('/'.$this->regex($tags).'/s', $content);
    }

    public function process(string $content, bool $ignoreHtml = false): string
    {
        if (! str_contains($content, '[') || empty($this->tags)) {
            return $content;
        }

        // Only consider tags that actually appear in the content.
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
        $tagnames = array_values(array_intersect($this->tags(), $matches[1]));
        if (! $tagnames) {
            return $content;
        }

        $content = $this->unautop($content, $tagnames);

        if ($ignoreHtml) {
            // Don't process shortcodes that live inside HTML attributes.
            $content = preg_replace_callback('/<[^>]*>/', fn ($m) => str_replace(['[', ']'], ['&#91;', '&#93;'], $m[0]), $content);
        }

        $pattern = $this->regex($tagnames);

        $result = preg_replace_callback("/{$pattern}/s", fn (array $m) => $this->doTag($m), $content);

        return $result ?? $content;
    }

    /**
     * Remove all registered shortcodes from the content (e.g. for excerpts).
     */
    public function strip(string $content): string
    {
        if (! str_contains($content, '[') || empty($this->tags)) {
            return $content;
        }

        $pattern = $this->regex($this->tags());

        return preg_replace_callback("/{$pattern}/s", function ($m) {
            // Keep escaped shortcodes as literal text.
            if ($m[1] === '[' && $m[6] === ']') {
                return substr($m[0], 1, -1);
            }

            return $m[1].$m[6];
        }, $content) ?? $content;
    }

    /**
     * Combine user attributes with known defaults (like shortcode_atts()).
     */
    public function atts(array $defaults, array|string|null $atts, string $shortcode = ''): array
    {
        $atts = (array) $atts;
        $out = [];
        foreach ($defaults as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }

        if ($shortcode !== '' && $this->filter) {
            $out = ($this->filter)("shortcode_atts_{$shortcode}", $out, $defaults, $atts, $shortcode);
        }

        return $out;
    }

    /**
     * Parse an attribute string into an array.
     */
    public function parseAtts(string $text): array
    {
        $atts = [];
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text) ?? $text;
        // Normalise "smart" quotes that rich text editors sometimes produce.
        $text = str_replace(['“', '”', '„', '″'], '"', $text);
        $text = str_replace(['‘', '’', '′'], "'", $text);

        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';

        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (! empty($m[1])) {
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (! empty($m[3])) {
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (! empty($m[5])) {
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && strlen($m[7])) {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8]) && strlen($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                } elseif (isset($m[9])) {
                    $atts[] = stripcslashes($m[9]);
                }
            }

            // Reject any unclosed HTML elements.
            foreach ($atts as &$value) {
                if (is_string($value) && str_contains($value, '<') && preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value) !== 1) {
                    $value = '';
                }
            }
            unset($value);
        } else {
            $atts = ltrim($text) === '' ? [] : [ltrim($text)];
        }

        return $atts;
    }

    /* ------------------------------------------------------------------ */

    protected function doTag(array $m): string
    {
        // Escaped: [[tag]] -> [tag]
        if ($m[1] === '[' && $m[6] === ']') {
            return substr($m[0], 1, -1);
        }

        $tag = $m[2];
        $atts = $this->parseAtts($m[3]);
        $content = isset($m[5]) && $m[5] !== '' ? $m[5] : null;

        if ($this->filter) {
            $pre = ($this->filter)('pre_do_shortcode_tag', false, $tag, $atts, $m);
            if ($pre !== false) {
                return $m[1].$pre.$m[6];
            }
        }

        $callback = $this->tags[$tag] ?? null;
        if (! $callback) {
            return $m[0];
        }

        $output = (string) call_user_func($callback, $atts, $content, $tag);

        if ($this->filter) {
            $output = (string) ($this->filter)('do_shortcode_tag', $output, $tag, $atts, $m);
        }

        return $m[1].$output.$m[6];
    }

    /**
     * Rich text editors wrap a lone shortcode in <p>…</p>. Since shortcodes
     * often output block-level HTML, unwrap them (like shortcode_unautop()).
     */
    protected function unautop(string $content, array $tagnames): string
    {
        $tagregexp = implode('|', array_map(fn ($t) => preg_quote($t, '/'), $tagnames));

        $pattern = '/<p>(?:\s|&nbsp;|<br\s*\/?>)*'
            .'(\[(' . $tagregexp . ')(?![\w-])[^\]]*\](?:[\s\S]*?\[\/\2\])?)'
            .'(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i';

        return preg_replace($pattern, '$1', $content) ?? $content;
    }

    /**
     * Port of WordPress get_shortcode_regex().
     */
    public function regex(array $tagnames): string
    {
        $tagregexp = implode('|', array_map(fn ($t) => preg_quote($t, '/'), $tagnames));

        return '\\['                              // Opening bracket.
            .'(\\[?)'                             // 1: Optional second opening bracket for escaping: [[tag]].
            ."($tagregexp)"                       // 2: Shortcode name.
            .'(?![\\w-])'                         // Not followed by word character or hyphen.
            .'('                                  // 3: Unroll the loop: inside the opening shortcode tag.
            .'[^\\]\\/]*'                         // Not a closing bracket or forward slash.
            .'(?:'
            .'\\/(?!\\])'                         // A forward slash not followed by a closing bracket.
            .'[^\\]\\/]*'                         // Not a closing bracket or forward slash.
            .')*?'
            .')'
            .'(?:'
            .'(\\/)'                              // 4: Self closing tag...
            .'\\]'                                // ...and closing bracket.
            .'|'
            .'\\]'                                // Closing bracket.
            .'(?:'
            .'('                                  // 5: Content between the opening and closing tags.
            .'[^\\[]*+'                           // Not an opening bracket.
            .'(?:'
            .'\\[(?!\\/\\2\\])'                   // An opening bracket not followed by the closing tag.
            .'[^\\[]*+'                           // Not an opening bracket.
            .')*+'
            .')'
            .'\\[\\/\\2\\]'                       // Closing shortcode tag.
            .')?'
            .')'
            .'(\\]?)';                            // 6: Optional second closing bracket for escaping.
    }
}
