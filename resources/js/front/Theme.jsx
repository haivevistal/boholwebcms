import { useEffect, useMemo, useState } from 'react';
import { useRegistry } from '../shared/registry';
import { AdminBar, Comments, Content, CookieNotice, DocumentHead, Flash, Menu, Pagination, PasswordForm, SearchForm, SmartLink, ThemeContext } from './theme-api';

/**
 * The single Inertia page for the whole front end. It merges the active
 * theme's template map (parent → child), then picks the first template in
 * the server-provided hierarchy (e.g. ["single-product", "single", "singular", "index"]).
 * Templates registered by plugins are used when the theme lacks them.
 */
export default function Theme(props) {
    const CMS = window.CMS;
    const themes = useRegistry(CMS.registries.themes);
    const pluginTemplates = useRegistry(CMS.registries.templates);
    const [preview, setPreview] = useState(null);
    const [gaveUp, setGaveUp] = useState(false);

    const stack = props.theme.stack;
    const missing = stack.filter((slug) => !themes[slug]);

    useEffect(() => {
        if (!missing.length) return;
        const t = setTimeout(() => setGaveUp(true), 2500);
        return () => clearTimeout(t);
    }, [missing.length]);

    // Live Customizer preview (postMessage from /admin/customize).
    useEffect(() => {
        if (!props.customizePreview || window.parent === window) return;
        const onMessage = (e) => {
            if (e.origin === window.location.origin && e.data?.type === 'cms:customize') setPreview(e.data);
        };
        window.addEventListener('message', onMessage);
        window.parent.postMessage({ type: 'cms:customize-ready' }, window.location.origin);
        return () => window.removeEventListener('message', onMessage);
    }, [props.customizePreview]);

    const ctx = useMemo(() => CMS.hooks.applyFilters('theme.props', withPreview(props, preview)), [props, preview]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        CMS.hooks.doAction('theme.rendered', ctx);
    }, [ctx]); // eslint-disable-line react-hooks/exhaustive-deps

    // Accent colour & custom CSS from the customizer preview.
    useEffect(() => {
        if (!preview) return;
        let el = document.getElementById('cms-preview-css');
        if (!el) {
            el = document.createElement('style');
            el.id = 'cms-preview-css';
            document.head.appendChild(el);
        }
        el.textContent = preview.mods?.custom_css || '';
    }, [preview]);

    if (missing.length && !gaveUp) return null;

    const merged = stack.reduce(
        (acc, slug) => {
            const def = themes[slug];
            if (!def) return acc;
            return { templates: { ...acc.templates, ...(def.templates || {}) }, Layout: def.Layout || acc.Layout };
        },
        { templates: {}, Layout: null },
    );

    const templates = CMS.hooks.applyFilters('theme.templates', merged.templates, ctx);
    const name = ctx.view.templates.find((t) => templates[t]) ?? ctx.view.templates.find((t) => pluginTemplates[t]);
    const Template = (name && (templates[name] || pluginTemplates[name])) || FallbackTemplate;
    const Layout = merged.Layout;

    return (
        <ThemeContext.Provider value={{ ...ctx, template: name || 'fallback' }}>
            <DocumentHead />
            <AdminBar />
            {Layout ? (
                <Layout {...ctx}>
                    <Template {...ctx} />
                </Layout>
            ) : (
                <Template {...ctx} />
            )}
            <Flash />
            <CookieNotice />
        </ThemeContext.Provider>
    );
}

function withPreview(props, preview) {
    if (!preview) return props;
    const mods = { ...props.theme.mods, ...(preview.mods || {}) };
    const o = preview.options || {};
    const site = { ...props.site };
    if (o.blogname !== undefined) site.name = o.blogname;
    if (o.blogdescription !== undefined) site.description = o.blogdescription;
    if (mods.display_header_text !== undefined) site.show_title = !!mods.display_header_text;
    if (preview.mods && 'custom_logo' in preview.mods) {
        const url = preview.media?.[preview.mods.custom_logo];
        site.logo = preview.mods.custom_logo && url ? { url, alt: site.name } : null;
    }
    const media = { ...(props.theme.media || {}), ...(preview.media || {}) };
    return { ...props, site, theme: { ...props.theme, mods, media } };
}

/**
 * Used when the theme bundle is missing (not built yet) or implements no
 * matching template — keeps the site readable.
 */
function FallbackTemplate(ctx) {
    const { site, view } = ctx;
    const posts = view.posts || [];
    return (
        <div className="cms-theme-missing">
            <header style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 16, borderBottom: '1px solid #e2e8f0', paddingBottom: 16 }}>
                <div>
                    <SmartLink href={site.url} style={{ fontSize: 24, fontWeight: 700, color: 'inherit', textDecoration: 'none' }}>
                        {site.name}
                    </SmartLink>
                    <div style={{ opacity: 0.7 }}>{site.description}</div>
                </div>
                <nav>
                    <Menu location="primary" className="fallback-menu" />
                </nav>
            </header>
            <p className="notice" style={{ marginTop: 16 }}>
                The active theme’s bundle isn’t loaded (or has no template for <code>{view.templates[0]}</code>). Run <code>npm run build:themes</code>.
            </p>
            <main style={{ marginTop: 24 }}>
                {view.kind === '404' ? (
                    <>
                        <h1>Page not found</h1>
                        <SearchForm />
                    </>
                ) : view.post ? (
                    <article>
                        <h1>{view.post.title}</h1>
                        {view.post.password_required ? <PasswordForm post={view.post} /> : <Content html={view.post.content} />}
                        <Comments />
                    </article>
                ) : (
                    <>
                        <h1>{view.title}</h1>
                        {posts.map((p) => (
                            <article key={p.id} style={{ margin: '24px 0' }}>
                                <h2>
                                    <SmartLink href={p.permalink}>{p.title}</SmartLink>
                                </h2>
                                <small>{p.date_formatted}</small>
                                <p>{p.excerpt}</p>
                            </article>
                        ))}
                        <Pagination pagination={view.pagination} />
                    </>
                )}
            </main>
        </div>
    );
}
