import { Link } from '@inertiajs/react';
import { Comments, Content, PasswordForm, Slot, useThemeMod } from '@boholwebcms/theme';
import { PostMeta, WithSidebar } from '../components';

export default function Single({ view }) {
    const post = view.post;
    const showAuthor = useThemeMod('show_author_box', true);
    const tags = post.terms?.post_tag || [];

    return (
        <WithSidebar>
            <article className="entry">
                <header className="entry__header">
                    <h1 className="entry__title">{post.title}</h1>
                    <PostMeta post={post} />
                </header>
                {post.featured_image && (
                    <figure className="entry__image">
                        <img src={post.featured_image.sizes?.large || post.featured_image.url} alt={post.featured_image.alt || ''} />
                        {post.featured_image.caption && <figcaption>{post.featured_image.caption}</figcaption>}
                    </figure>
                )}
                <Slot name="before_post_content" post={post} />
                {post.password_required ? <PasswordForm post={post} /> : <Content html={post.content} />}
                <Slot name="after_post_content" post={post} />

                {tags.length > 0 && (
                    <div className="entry__tags">
                        {tags.map((t) => (
                            <Link key={t.id} href={t.link}>
                                #{t.name}
                            </Link>
                        ))}
                    </div>
                )}

                {showAuthor && post.author && post.type === 'post' && (
                    <aside className="author-box">
                        <img src={post.author.avatar} alt="" />
                        <div>
                            <p className="author-box__label">Written by</p>
                            <Link href={post.author.url} className="author-box__name">
                                {post.author.name}
                            </Link>
                            {post.author.bio && <p>{post.author.bio}</p>}
                        </div>
                    </aside>
                )}

                {(post.previous || post.next) && (
                    <nav className="post-nav">
                        {post.previous ? (
                            <Link href={post.previous.url} className="post-nav__link">
                                <span>← Previous</span>
                                {post.previous.title}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {post.next && (
                            <Link href={post.next.url} className="post-nav__link post-nav__link--next">
                                <span>Next →</span>
                                {post.next.title}
                            </Link>
                        )}
                    </nav>
                )}

                <Comments />
            </article>
        </WithSidebar>
    );
}
