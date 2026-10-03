import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Menu, Region, SearchForm, useTheme, useThemeMod } from '@boholwebcms/theme';

const FONTS = {
    sans: "'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
    serif: "'Iowan Old Style', 'Palatino Linotype', Palatino, Georgia, serif",
    mono: "ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace",
};

export default function Layout({ children }) {
    const { site, view, template } = useTheme();
    const accent = useThemeMod('accent_color', '#4f46e5');
    const headerStyle = useThemeMod('header_style', 'light');
    const font = useThemeMod('body_font', 'sans');
    const width = useThemeMod('content_width', 1180);
    const footerText = useThemeMod('footer_text', '© {year} {site}.');
    const [menuOpen, setMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => router.on('navigate', () => (setMenuOpen(false), setSearchOpen(false))), []);

    const landing = template === 'template-landing' || view?.post?.template === 'landing';
    const style = { '--accent': accent, '--font': FONTS[font] || FONTS.sans, '--width': `${width}px` };

    if (landing) {
        return (
            <div className="aurora" style={style}>
                {children}
            </div>
        );
    }

    return (
        <div className="aurora" style={style}>
            <a className="skip-link" href="#content">
                Skip to content
            </a>
            <header className={`site-header site-header--${headerStyle}`}>
                <div className="container site-header__inner">
                    <Link href={site.url} className="brand">
                        {site.logo ? <img src={site.logo.url} alt={site.logo.alt} className="brand__logo" /> : <span className="brand__mark">{(site.name || 'A')[0]}</span>}
                        {site.show_title !== false && (
                            <span className="brand__text">
                                <span className="brand__name">{site.name}</span>
                                {site.description && <span className="brand__tagline">{site.description}</span>}
                            </span>
                        )}
                    </Link>
                    <button className="nav-toggle" aria-expanded={menuOpen} aria-label="Toggle navigation" onClick={() => setMenuOpen((o) => !o)}>
                        <span />
                        <span />
                        <span />
                    </button>
                    <nav className={`primary-nav ${menuOpen ? 'is-open' : ''}`} aria-label="Primary">
                        <Menu location="primary" className="menu" />
                        <button className="search-toggle" aria-label="Search" onClick={() => setSearchOpen((o) => !o)}>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-3.5-3.5" />
                            </svg>
                        </button>
                    </nav>
                </div>
                {searchOpen && (
                    <div className="header-search">
                        <div className="container">
                            <SearchForm placeholder="Search the site…" />
                        </div>
                    </div>
                )}
            </header>

            <Region name="before_content" className="container before-content" />

            <div id="content" className="site-content">
                {children}
            </div>

            <footer className="site-footer">
                <Region name="footer" className="container footer-widgets" />
                <div className="container site-footer__bottom">
                    <p>{footerText.replace('{year}', new Date().getFullYear()).replace('{site}', site.name)}</p>
                    <Menu location="footer" className="footer-menu" depth={1} />
                </div>
            </footer>
        </div>
    );
}
