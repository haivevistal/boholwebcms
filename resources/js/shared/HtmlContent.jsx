import { router } from '@inertiajs/react';
import { createElement, useEffect, useRef } from 'react';

/**
 * Renders server HTML (post content with shortcodes, plugin admin pages,
 * widget output) inside the React app while keeping it "alive":
 *
 *  - internal <a> clicks become Inertia visits (no full reload)
 *  - <form> submissions to same-origin URLs go through Inertia
 *    (add data-cms-native to a form/link to opt out)
 *  - inline <script> tags run (e.g. scripts printed by shortcodes)
 *  - a "cms:content" DOM event fires after each render so plugin scripts can
 *    bind behaviour:  document.addEventListener('cms:content', e => init(e.detail.element))
 */
export default function HtmlContent({ html, as = 'div', className, interceptForms = true, ...rest }) {
    const ref = useRef(null);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        // Execute inline/external scripts inserted via innerHTML.
        el.querySelectorAll('script').forEach((old) => {
            const script = document.createElement('script');
            for (const attr of old.attributes) script.setAttribute(attr.name, attr.value);
            script.text = old.textContent;
            old.replaceWith(script);
        });

        el.dispatchEvent(new CustomEvent('cms:content', { bubbles: true, detail: { element: el } }));
        window.CMS?.hooks?.doAction('content.rendered', el);
    }, [html]);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const sameOrigin = (url) => {
            try {
                return new URL(url, window.location.href).origin === window.location.origin;
            } catch {
                return false;
            }
        };

        const onClick = (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            const a = e.target.closest('a');
            if (!a || !el.contains(a)) return;
            const href = a.getAttribute('href');
            if (
                !href ||
                href.startsWith('#') ||
                a.target === '_blank' ||
                a.hasAttribute('download') ||
                a.hasAttribute('data-cms-native') ||
                !sameOrigin(a.href) ||
                /\.(pdf|zip|jpe?g|png|gif|webp|svg|mp4|mp3|docx?|xlsx?|csv)$/i.test(new URL(a.href).pathname) ||
                /^\/(logout|storage|content)\b/.test(new URL(a.href).pathname)
            ) {
                return;
            }
            e.preventDefault();
            router.visit(a.href);
        };

        const onSubmit = (e) => {
            const form = e.target;
            if (!interceptForms || !(form instanceof HTMLFormElement) || form.hasAttribute('data-cms-native')) return;
            const action = e.submitter?.getAttribute('formaction') || form.getAttribute('action') || window.location.href;
            if (!sameOrigin(action)) return;
            e.preventDefault();

            const data = new FormData(form);
            if (e.submitter?.name) data.append(e.submitter.name, e.submitter.value);
            const method = (data.get('_method') || form.getAttribute('method') || 'get').toLowerCase();

            if (method === 'get') {
                router.get(action, Object.fromEntries(data.entries()), { preserveScroll: true });
            } else {
                router.post(action, data, { preserveScroll: true, forceFormData: true });
            }
        };

        el.addEventListener('click', onClick);
        el.addEventListener('submit', onSubmit);
        return () => {
            el.removeEventListener('click', onClick);
            el.removeEventListener('submit', onSubmit);
        };
    }, [interceptForms]);

    return createElement(as, { ref, className, dangerouslySetInnerHTML: { __html: html || '' }, ...rest });
}
