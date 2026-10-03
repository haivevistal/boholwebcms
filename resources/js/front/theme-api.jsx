import { createContext, useContext, useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import HtmlContent from '../shared/HtmlContent';

/*
 * `@boholwebcms/theme` — helpers every theme can import (the theme-kit maps the
 * import to window.CMS.theme). Using them keeps themes small and makes
 * plugins & core features (comments, menus, regions, admin bar) work in
 * every theme automatically.
 */

export const ThemeContext = createContext(null);

/** All theme props: site, theme (mods), view, menus, regions, comments, ... */
export function useTheme() {
    return useContext(ThemeContext);
}

/** A Customizer value (theme_mod) with live-preview support. */
export function useThemeMod(name, fallback) {
    const ctx = useTheme();
    const value = ctx?.theme?.mods?.[name];
    return value === undefined || value === null || value === '' ? fallback : value;
}

/** URL of an image chosen in a Customizer "media" control: useThemeMedia(useThemeMod('hero_image')) */
export function useThemeMedia(id) {
    const ctx = useTheme();
    return id ? ctx?.theme?.media?.[id] ?? null : null;
}

/** Run a JS filter registered by plugins: CMS.hooks.addFilter(name, ns, fn) */
export function applyFilters(name, value, ...args) {
    return window.CMS.hooks.applyFilters(name, value, ...args);
}

export function doAction(name, ...args) {
    window.CMS.hooks.doAction(name, ...args);
}

/** Post content / any server HTML (shortcodes already rendered on the server). */
export function Content({ html, className = 'entry-content', as = 'div' }) {
    return <HtmlContent html={html} className={className} as={as} />;
}

/**
 * A theme region (sidebar, footer, ...) declared in theme.json. Plugins fill it
 * from PHP (add_action('cms_region_sidebar', fn () => print '...')) or from JS
 * (CMS.hooks.addFilter('theme.region.sidebar', 'ns', (items) => [...items, <Comp/>])).
 * `children` render when nothing else is hooked in.
 */
export function Region({ name, className, children, as = 'div' }) {
    const ctx = useTheme();
    const html = ctx?.regions?.[name];
    const extra = applyFilters(`theme.region.${name}`, [], ctx);
    if (!html && !extra.length && !children) return null;
    const Tag = as;
    return (
        <Tag className={className} data-region={name}>
            {html ? <HtmlContent html={html} /> : !extra.length ? children : null}
            {extra.map((node, i) => (
                <div key={i}>{node}</div>
            ))}
        </Tag>
    );
}

/**
 * Named insertion point for plugin React components:
 *   <Slot name="after_post" post={post} />
 *   CMS.hooks.addFilter('theme.slot.after_post', 'my-plugin', (nodes, props) => [...nodes, <Share {...props} />]);
 */
export function Slot({ name, ...props }) {
    const nodes = applyFilters(`theme.slot.${name}`, [], props, useTheme());
    return nodes.length ? nodes.map((n, i) => <span key={i} style={{ display: 'contents' }}>{n}</span>) : null;
}

/** Navigation menu for a location from theme.json "menus". */
export function Menu({ location = 'primary', className, itemClassName, activeClassName = 'is-active', depth = 2, renderItem }) {
    const ctx = useTheme();
    const items = ctx?.menus?.[location] || [];
    const render = (list, level) => (
        <ul className={level === 0 ? className : 'sub-menu'}>
            {list.map((item) => (
                <li key={item.id ?? item.url} className={[itemClassName, item.active && activeClassName, item.children?.length && 'has-children'].filter(Boolean).join(' ')}>
                    {renderItem ? renderItem(item) : <SmartLink href={item.url}>{item.title}</SmartLink>}
                    {item.children?.length > 0 && level + 1 < depth && render(item.children, level + 1)}
                </li>
            ))}
        </ul>
    );
    return items.length ? render(items, 0) : null;
}

/** Inertia <Link> for internal URLs, plain <a> for external ones. */
export function SmartLink({ href, children, ...props }) {
    let internal = false;
    try {
        const url = new URL(href, window.location.href);
        internal = url.origin === window.location.origin && !/^\/(admin|login|logout|storage|content)(\/|$)/.test(url.pathname);
    } catch {
        internal = false;
    }
    return internal ? (
        <Link href={href} {...props}>
            {children}
        </Link>
    ) : (
        <a href={href} {...props}>
            {children}
        </a>
    );
}

export function Pagination({ pagination, className = 'pagination', prevLabel = '← Newer', nextLabel = 'Older →' }) {
    if (!pagination || pagination.last <= 1) return null;
    return (
        <nav className={className} aria-label="Pagination">
            {pagination.prev_url ? <Link href={pagination.prev_url} className="pagination__prev">{prevLabel}</Link> : <span />}
            <span className="pagination__pages">
                {pagination.pages.map((p) =>
                    p.current ? (
                        <span key={p.number} aria-current="page" className="pagination__page is-current">
                            {p.number}
                        </span>
                    ) : (
                        <Link key={p.number} href={p.url} className="pagination__page">
                            {p.number}
                        </Link>
                    ),
                )}
            </span>
            {pagination.next_url ? <Link href={pagination.next_url} className="pagination__next">{nextLabel}</Link> : <span />}
        </nav>
    );
}

export function SearchForm({ className = 'search-form', placeholder = 'Search…', buttonLabel = 'Search' }) {
    const ctx = useTheme();
    const [q, setQ] = useState(ctx?.view?.search_query || '');
    return (
        <form
            role="search"
            className={className}
            onSubmit={(e) => {
                e.preventDefault();
                router.get(ctx?.site?.search_url || '/', { s: q });
            }}
        >
            <input type="search" name="s" value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} aria-label="Search" />
            <button type="submit">{buttonLabel}</button>
        </form>
    );
}

/** Comments list (threaded) + form, wired to Discussion settings. */
export function Comments({ className = 'comments', title }) {
    const ctx = useTheme();
    const comments = ctx?.comments;
    const [replyTo, setReplyTo] = useState(null);
    if (!comments) return null;
    if (!comments.open && !comments.items.length) return null;

    const children = (parentId) => comments.items.filter((c) => (c.parent_id ?? null) === parentId);
    const renderList = (parentId, depth) => {
        const list = children(parentId);
        if (!list.length) return null;
        return (
            <ol className={depth === 1 ? 'comment-list' : 'children'}>
                {list.map((c) => (
                    <li key={c.id} id={`comment-${c.id}`} className={`comment depth-${depth}${c.status !== 'approved' ? ' is-pending' : ''}`}>
                        <article className="comment-body">
                            <header className="comment-meta">
                                {comments.show_avatars && <img className="avatar" src={c.avatar} alt="" width="40" height="40" loading="lazy" />}
                                <span className="comment-author">{c.author_url ? <a href={c.author_url} rel="nofollow ugc noreferrer" target="_blank">{c.author}</a> : c.author}</span>
                                <time className="comment-date" dateTime={c.date}>{c.date_formatted}</time>
                            </header>
                            {c.status !== 'approved' && <p className="comment-awaiting-moderation">Your comment is awaiting moderation.</p>}
                            <div className="comment-content" dangerouslySetInnerHTML={{ __html: c.content }} />
                            {comments.open && comments.threaded && depth < comments.max_depth && (
                                <button type="button" className="comment-reply-link" onClick={() => setReplyTo(c)}>
                                    Reply
                                </button>
                            )}
                        </article>
                        {replyTo?.id === c.id && <CommentForm parent={c} onCancel={() => setReplyTo(null)} />}
                        {renderList(c.id, depth + 1)}
                    </li>
                ))}
            </ol>
        );
    };

    return (
        <section className={className} id="comments">
            {comments.count > 0 && <h2 className="comments-title">{title ?? `${comments.count} ${comments.count === 1 ? 'Comment' : 'Comments'}`}</h2>}
            {renderList(null, 1)}
            {comments.open ? !replyTo && <CommentForm /> : <p className="no-comments">Comments are closed.</p>}
        </section>
    );
}

export function CommentForm({ parent = null, onCancel }) {
    const ctx = useTheme();
    const c = ctx.comments;
    const form = useForm({ post_id: c.post_id, parent_id: parent?.id ?? null, content: '', author_name: '', author_email: '', author_url: '', website: '' });

    if (c.registration_required && !c.logged_in) {
        return (
            <p className="must-log-in">
                You must be <a href={`${ctx.site.login_url}?redirect_to=${encodeURIComponent(window.location.pathname)}`}>logged in</a> to post a comment.
            </p>
        );
    }

    return (
        <form
            className="comment-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(c.action, { preserveScroll: true, onSuccess: () => (form.reset('content'), onCancel?.()) });
            }}
        >
            <h3 className="comment-reply-title">
                {parent ? `Reply to ${parent.author}` : 'Leave a Reply'}{' '}
                {onCancel && (
                    <button type="button" className="cancel-reply" onClick={onCancel}>
                        Cancel
                    </button>
                )}
            </h3>
            {ctx.site.user ? (
                <p className="logged-in-as">
                    Logged in as {ctx.site.user.name}. {ctx.site.logout_url && <a href={ctx.site.logout_url}>Log out?</a>}
                </p>
            ) : (
                <div className="comment-form-author-fields">
                    <p>
                        <label>
                            Name{c.require_name_email && ' *'}
                            <input type="text" value={form.data.author_name} onChange={(e) => form.setData('author_name', e.target.value)} required={c.require_name_email} autoComplete="name" />
                        </label>
                        {form.errors.author_name && <span className="error">{form.errors.author_name}</span>}
                    </p>
                    <p>
                        <label>
                            Email{c.require_name_email && ' *'}
                            <input type="email" value={form.data.author_email} onChange={(e) => form.setData('author_email', e.target.value)} required={c.require_name_email} autoComplete="email" />
                        </label>
                        {form.errors.author_email && <span className="error">{form.errors.author_email}</span>}
                    </p>
                    <p>
                        <label>
                            Website
                            <input type="url" value={form.data.author_url} onChange={(e) => form.setData('author_url', e.target.value)} autoComplete="url" />
                        </label>
                    </p>
                </div>
            )}
            <p>
                <label>
                    Comment *
                    <textarea rows={6} value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} required />
                </label>
                {form.errors.content && <span className="error">{form.errors.content}</span>}
            </p>
            {/* honeypot */}
            <input type="text" name="website" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} tabIndex={-1} autoComplete="off" style={{ position: 'absolute', left: '-9999px' }} aria-hidden="true" />
            <p className="form-submit">
                <button type="submit" disabled={form.processing}>
                    {form.processing ? 'Posting…' : 'Post Comment'}
                </button>
            </p>
        </form>
    );
}

export function PasswordForm({ post, className = 'post-password-form' }) {
    const form = useForm({ password: '' });
    return (
        <form
            className={className}
            onSubmit={(e) => {
                e.preventDefault();
                form.post(post.password_form_action);
            }}
        >
            <p>This content is password protected. To view it please enter your password below:</p>
            <p>
                <label>
                    Password: <input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                </label>{' '}
                <button type="submit">Enter</button>
            </p>
            {form.errors.password && <p className="error">{form.errors.password}</p>}
        </form>
    );
}

/** <title>/meta for client-side navigations (the first load is server-rendered). */
export function DocumentHead() {
    const ctx = useTheme();
    const meta = ctx?.meta || {};
    return (
        <Head>
            <title>{meta.title || ctx?.site?.name}</title>
            {meta.description && <meta name="description" content={meta.description} head-key="description" />}
        </Head>
    );
}

/** Shows server flash messages (e.g. "Thanks for your comment!"). */
export function Flash() {
    const { flash } = usePage().props;
    const [msg, setMsg] = useState(null);
    useEffect(() => {
        const m = flash?.error || flash?.success || flash?.info;
        if (!m) return;
        setMsg({ text: m, error: !!flash?.error });
        const t = setTimeout(() => setMsg(null), 6000);
        return () => clearTimeout(t);
    }, [flash]);
    return msg ? (
        <div className={`cms-flash${msg.error ? ' cms-flash--error' : ''}`} role="status" onClick={() => setMsg(null)}>
            {msg.text}
        </div>
    ) : null;
}

export function CookieNotice() {
    const ctx = useTheme();
    const [hidden, setHidden] = useState(() => {
        try {
            return localStorage.getItem('cms_cookie_ok') === '1';
        } catch {
            return false;
        }
    });
    if (!ctx?.privacy?.cookie_notice || hidden) return null;
    return (
        <div className="cms-cookie-notice" role="dialog" aria-live="polite">
            <div>{ctx.privacy.cookie_notice_text}</div>
            {ctx.privacy.policy_url && <SmartLink href={ctx.privacy.policy_url}>Privacy Policy</SmartLink>}
            <div>
                <button
                    type="button"
                    className="cms-button"
                    onClick={() => {
                        try {
                            localStorage.setItem('cms_cookie_ok', '1');
                        } catch {}
                        setHidden(true);
                    }}
                >
                    OK
                </button>
            </div>
        </div>
    );
}

export function AdminBar() {
    const ctx = useTheme();
    if (!ctx?.adminBar || ctx.customizePreview) return null;
    return (
        <div className="cms-admin-bar">
            <a href="/admin" className="cms-admin-bar__logo">{(window.CMS?.name || 'P')[0]}</a>
            {ctx.adminBar.items.map((item) => (
                <a key={item.id} href={item.url}>
                    {item.title}
                </a>
            ))}
            <span className="cms-admin-bar__spacer" />
            {ctx.site.user && <span style={{ opacity: 0.8 }}>Howdy, {ctx.site.user.name}</span>}
            {ctx.site.logout_url && <a href={ctx.site.logout_url}>Log Out</a>}
        </div>
    );
}

export function formatDate(iso, options = { year: 'numeric', month: 'long', day: 'numeric' }) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(document.documentElement.lang || undefined, options);
}

export { Head, Link, router, useForm, usePage, HtmlContent };
