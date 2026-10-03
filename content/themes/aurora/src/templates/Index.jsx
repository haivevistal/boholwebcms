import { Pagination } from '@boholwebcms/theme';
import { PageHeader, PostGrid, WithSidebar } from '../components';

export default function Index({ view, site }) {
    const isHome = view.is_home && view.kind !== 'posts_page';
    return (
        <>
            {!isHome && <PageHeader kicker="Blog" title={view.title} />}
            {isHome && <PageHeader kicker={site.name} title="Latest posts" />}
            <WithSidebar>
                <PostGrid posts={view.posts} />
                <Pagination pagination={view.pagination} />
            </WithSidebar>
        </>
    );
}
