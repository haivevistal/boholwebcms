import './style.css';
import { Link } from '@inertiajs/react';
import { Comments, Content, Menu, Pagination, PasswordForm, Region, SearchForm, useTheme, useThemeMod } from '@boholwebcms/theme';

function Layout({ children }) {
    const { site } = useTheme();
    const accent = useThemeMod('accent_color', '#b45309');
    const scheme = useThemeMod('color_scheme', 'light');
    return (
        <div className={`mj mj--${scheme}`} style={{ '--accent': accent }}>
            <header className="mj-header">
                <Link href={site.url} className="mj-title">
                    {site.logo ? <img src={site.logo.url} alt={site.logo.alt} /> : site.name}
                </Link>
                {site.description && <p className="mj-tagline">{site.description}</p>}
                <Menu location="primary" className="mj-menu" depth={1} />
            </header>
            <main className="mj-main">{children}</main>
            <footer className="mj-footer">
                <Region name="footer" />
                <p>
                    © {new Date().getFullYear()} {site.name}
                </p>
            </footer>
        </div>
    );
}

function List({ view }) {
    const excerpts = useThemeMod('show_excerpts', true);
    return (
        <>
            {!view.is_home && <h1 className="mj-archive-title">{view.title}</h1>}
            {view.posts?.length ? (
                view.posts.map((p) => (
                    <article key={p.id} className="mj-item">
                        <time dateTime={p.date}>{p.date_formatted}</time>
                        <h2>
                            <Link href={p.permalink}>{p.title || '(no title)'}</Link>
                        </h2>
                        {excerpts && p.excerpt && <p>{p.excerpt}</p>}
                    </article>
                ))
            ) : (
                <p>Nothing here yet.</p>
            )}
            <Pagination pagination={view.pagination} prevLabel="← Newer" nextLabel="Older →" />
        </>
    );
}

function Single({ view }) {
    const post = view.post;
    return (
        <article className="mj-entry">
            {post.type !== 'page' && <time dateTime={post.date}>{post.date_formatted}</time>}
            <h1>{post.title}</h1>
            {post.featured_image && <img className="mj-feature" src={post.featured_image.sizes?.large || post.featured_image.url} alt={post.featured_image.alt || ''} />}
            {post.password_required ? <PasswordForm post={post} /> : <Content html={post.content} />}
            {post.terms?.post_tag?.length > 0 && (
                <p className="mj-tags">
                    {post.terms.post_tag.map((t) => (
                        <Link key={t.id} href={t.link}>
                            #{t.name}
                        </Link>
                    ))}
                </p>
            )}
            <Comments />
        </article>
    );
}

function NotFound() {
    return (
        <div className="mj-entry">
            <h1>Not found</h1>
            <p>Sorry, that page doesn’t exist. Try a search:</p>
            <SearchForm />
        </div>
    );
}

export default {
    Layout,
    templates: { index: List, home: List, archive: List, search: List, single: Single, page: Single, 404: NotFound },
};
