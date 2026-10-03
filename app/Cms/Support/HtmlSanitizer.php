<?php

namespace App\Cms\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Small allow-list HTML sanitizer (the kses_post() equivalent). Used for
 * content saved by users who lack the "unfiltered_html" capability and
 * for comments. Allowed tags/attributes are filterable via "kses_allowed_html".
 */
class HtmlSanitizer
{
    protected const GLOBAL_ATTRS = ['class', 'id', 'style', 'title', 'lang', 'dir', 'role', 'aria-label', 'aria-hidden'];

    protected const TAGS = [
        'a' => ['href', 'target', 'rel', 'name'],
        'abbr' => [], 'b' => [], 'blockquote' => ['cite'], 'br' => [], 'caption' => [], 'cite' => [], 'code' => [],
        'col' => ['span'], 'colgroup' => ['span'], 'dd' => [], 'del' => ['datetime'], 'details' => ['open'], 'div' => ['align'],
        'dl' => [], 'dt' => [], 'em' => [], 'figcaption' => [], 'figure' => [], 'h1' => [], 'h2' => [], 'h3' => [],
        'h4' => [], 'h5' => [], 'h6' => [], 'hr' => [], 'i' => [], 'img' => ['src', 'alt', 'width', 'height', 'loading', 'srcset', 'sizes'],
        'ins' => ['datetime'], 'kbd' => [], 'li' => ['value'], 'mark' => [], 'ol' => ['start', 'reversed', 'type'], 'p' => ['align'],
        'pre' => [], 'q' => ['cite'], 's' => [], 'small' => [], 'span' => [], 'strike' => [], 'strong' => [], 'sub' => [],
        'summary' => [], 'sup' => [], 'table' => ['border', 'cellpadding', 'cellspacing', 'width'], 'tbody' => [], 'td' => ['colspan', 'rowspan', 'align', 'valign'],
        'tfoot' => [], 'th' => ['colspan', 'rowspan', 'align', 'valign', 'scope'], 'thead' => [], 'tr' => [], 'u' => [], 'ul' => [],
        'video' => ['src', 'controls', 'poster', 'width', 'height', 'loop', 'muted', 'autoplay', 'playsinline'],
        'audio' => ['src', 'controls', 'loop', 'muted'], 'source' => ['src', 'type'],
    ];

    public function clean(string $html, ?array $allowed = null): string
    {
        if (trim($html) === '') {
            return '';
        }

        $allowed ??= function_exists('apply_filters') ? apply_filters('kses_allowed_html', self::TAGS) : self::TAGS;

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return e(strip_tags($html));
        }

        $this->walk($root, $allowed);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    protected function walk(DOMNode $node, array $allowed): void
    {
        // Iterate over a static copy since we mutate the tree.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base'], true) && ! isset($allowed[$tag])) {
                $node->removeChild($child);

                continue;
            }

            if (! isset($allowed[$tag])) {
                // Unwrap unknown elements, keep their children.
                $this->walk($child, $allowed);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $permitted = array_merge(self::GLOBAL_ATTRS, $allowed[$tag]);
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = $attr->value;

                $isData = str_starts_with($name, 'data-');
                if ((! in_array($name, $permitted, true) && ! $isData) || str_starts_with($name, 'on')) {
                    $child->removeAttribute($attr->name);

                    continue;
                }

                if (in_array($name, ['href', 'src', 'cite', 'poster'], true) && preg_match('#^\s*(javascript|vbscript|data):#i', $value) && ! preg_match('#^\s*data:image/(png|jpe?g|gif|webp);#i', $value)) {
                    $child->removeAttribute($attr->name);
                }

                if ($name === 'style' && preg_match('/expression\s*\(|url\s*\(\s*[\'"]?\s*javascript:/i', $value)) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', trim($child->getAttribute('rel').' noopener'));
            }

            $this->walk($child, $allowed);
        }
    }
}
