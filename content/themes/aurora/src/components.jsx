import { Link } from '@inertiajs/react';
import { Region, SearchForm, Slot, useTheme, useThemeMod } from '@boholwebcms/theme';

export function PostMeta({ post }) {
    const cats = post.terms?.category || [];
    return (
        <div className="post-meta">
            {post.author && (
                <Link href={post.author.url} className="post-meta__author">
                    <img src={post.author.avatar} alt="" />
                    {post.author.name}
                </Link>
            )}
            <time dateTime={post.date}>{post.date_formatted}</time>
            {cats.length > 0 && (
                <span className="post-meta__cats">
                    {cats.map((c, i) => (
                        <span key={c.id}>
                            {i > 0 && ', '}
                            <Link href={c.link}>{c.name}</Link>
                        </span>
                    ))}
                </span>
            )}
            {post.comment_status === 'open' || post.comment_count > 0 ? (
                <a href={`${post.permalink}#comments`}>
                    {post.comment_count} {post.comment_count === 1 ? 'comment' : 'comments'}
                </a>
            ) : null}
        </div>
    );
}

export function PostCard({ post, layout = 'grid' }) {
    const cat = post.terms?.category?.[0];
    return (
        <article className={`card card--${layout}`}>
            <Link href={post.permalink} className="card__media" tabIndex={-1} aria-hidden="true">
                {post.featured_image ? (
                    <img src={post.featured_image.sizes?.['aurora-card'] || post.featured_image.sizes?.large || post.featured_image.url} alt={post.featured_image.alt || ''} loading="lazy" />
                ) : (
                    <span className="card__placeholder">{(post.title || '•')[0]}</span>
                )}
            </Link>
            <div className="card__body">
                {cat && (
                    <Link href={cat.link} className="card__kicker">
                        {cat.name}
                    </Link>
                )}
                <h2 className="card__title">
                    <Link href={post.permalink}>{post.title || '(no title)'}</Link>
                </h2>
                {post.excerpt && <p className="card__excerpt">{post.excerpt}</p>}
                <div className="card__footer">
                    <time dateTime={post.date}>{post.date_formatted}</time>
                    <Link href={post.permalink} className="card__more">
                        Read more →
                    </Link>
                </div>
            </div>
        </article>
    );
}

export function PostGrid({ posts }) {
    const layout = useThemeMod('blog_layout', 'grid');
    if (!posts?.length) return <p className="empty">Nothing found here yet.</p>;
    return (
        <div className={layout === 'list' ? 'post-list' : 'post-grid'}>
            {posts.map((p) => (
                <PostCard key={p.id} post={p} layout={layout} />
            ))}
        </div>
    );
}

export function Sidebar() {
    return (
        <aside className="sidebar">
            <section className="widget">
                <h3 className="widget-title">Search</h3>
                <SearchForm />
            </section>
            <Slot name="sidebar_top" />
            <Region name="sidebar" />
        </aside>
    );
}

export function WithSidebar({ children }) {
    const show = useThemeMod('show_sidebar', true);
    return (
        <div className={`container layout ${show ? 'layout--sidebar' : ''}`}>
            <div className="layout__main">{children}</div>
            {show && <Sidebar />}
        </div>
    );
}

export function PageHeader({ kicker, title, description }) {
    return (
        <header className="page-header">
            <div className="container">
                {kicker && <p className="page-header__kicker">{kicker}</p>}
                <h1 className="page-header__title">{title}</h1>
                {description && <p className="page-header__description">{description}</p>}
            </div>
        </header>
    );
}

export function useSiteName() {
    return useTheme().site.name;
}
