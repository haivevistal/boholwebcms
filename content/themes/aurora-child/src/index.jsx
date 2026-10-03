import { Link } from '@inertiajs/react';
import { applyFilters, useTheme, useThemeMod } from '@boholwebcms/theme';

/**
 * Only what differs from the parent: templates listed here override Aurora's
 * by name; everything else (Layout, single, page, ...) is inherited.
 */
function NotFound() {
    const { site } = useTheme();
    return (
        <section style={{ textAlign: 'center', padding: '120px 24px' }}>
            <p style={{ fontSize: 72, margin: 0 }}>🧭</p>
            <h1>Lost? (child theme 404)</h1>
            <p>This template is overridden by the Aurora Child theme.</p>
            <Link className="button" href={site.url}>
                Take me home
            </Link>
        </section>
    );
}

// Inject a promo banner above the content of every page via a JS filter.
window.CMS.hooks.addFilter('theme.region.before_content', 'aurora-child', (nodes) => [...nodes, <Promo key="promo" />]);

function Promo() {
    const text = useThemeMod('promo_text', '');
    if (!text) return null;
    return <div style={{ margin: '16px 0 0', padding: '12px 18px', borderRadius: 12, background: 'var(--accent-soft)', color: 'var(--accent-dark)', fontWeight: 600 }}>{applyFilters('aurora_child.promo_text', text)}</div>;
}

export default { templates: { 404: NotFound } };
