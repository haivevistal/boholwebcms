import { Link } from '@inertiajs/react';
import { Comments, Content, PasswordForm } from '@boholwebcms/theme';
import { PageHeader } from '../components';

export default function Page({ view, fullWidth = false, landing = false }) {
    const post = view.post;
    return (
        <>
            {!landing && <PageHeader title={post.title} />}
            {post.featured_image && !landing && (
                <div className="container">
                    <img className="page-hero-image" src={post.featured_image.sizes?.large || post.featured_image.url} alt={post.featured_image.alt || ''} />
                </div>
            )}
            <div className={`container page-body ${fullWidth ? '' : 'narrow'}`}>
                {post.password_required ? <PasswordForm post={post} /> : <Content html={post.content} />}
                {post.children?.length > 0 && (
                    <nav className="child-pages">
                        <h2>In this section</h2>
                        <ul>
                            {post.children.map((c) => (
                                <li key={c.url}>
                                    <Link href={c.url}>{c.title}</Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
                <Comments />
            </div>
        </>
    );
}
