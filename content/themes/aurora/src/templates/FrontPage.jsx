import { Link } from '@inertiajs/react';
import { Content, Pagination, SmartLink, useThemeMedia, useThemeMod } from '@boholwebcms/theme';
import { PostGrid } from '../components';

export default function FrontPage({ view }) {
    const heroEnabled = useThemeMod('hero_enabled', true);
    const title = useThemeMod('hero_title', 'Ideas worth sharing');
    const text = useThemeMod('hero_text', '');
    const buttonText = useThemeMod('hero_button_text', '');
    const buttonUrl = useThemeMod('hero_button_url', '#latest');
    const image = useThemeMedia(useThemeMod('hero_image'));

    const hero = heroEnabled && view.pagination?.current !== 2 && (
        <section className={`hero ${image ? 'hero--image' : ''}`} style={image ? { backgroundImage: `linear-gradient(rgb(15 23 42 / .55), rgb(15 23 42 / .55)), url(${image})` } : undefined}>
            <div className="container hero__inner">
                <h1 className="hero__title">{title}</h1>
                {text && <p className="hero__text">{text}</p>}
                {buttonText &&
                    (buttonUrl.startsWith('#') ? (
                        <a href={buttonUrl} className="button button--light">
                            {buttonText}
                        </a>
                    ) : (
                        <SmartLink href={buttonUrl} className="button button--light">
                            {buttonText}
                        </SmartLink>
                    ))}
            </div>
        </section>
    );

    // A static page was chosen as the homepage (Settings → Reading).
    if (view.post) {
        return (
            <>
                {hero}
                <div className="container narrow page-body">
                    <Content html={view.post.content} />
                </div>
            </>
        );
    }

    return (
        <>
            {hero}
            <section id="latest" className="container section">
                <div className="section__header">
                    <h2>Latest posts</h2>
                    {view.pagination?.next_url && <Link href={view.pagination.next_url}>Older posts →</Link>}
                </div>
                <PostGrid posts={view.posts} />
                <Pagination pagination={view.pagination} />
            </section>
        </>
    );
}
