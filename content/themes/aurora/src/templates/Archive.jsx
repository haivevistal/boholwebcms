import { Pagination } from '@boholwebcms/theme';
import { PageHeader, PostGrid, WithSidebar } from '../components';

export default function Archive({ view }) {
    const a = view.archive || {};
    const kicker = a.type === 'author' ? 'Author' : a.label || 'Archive';
    const description = a.term?.description || a.author?.bio || a.description;
    return (
        <>
            <PageHeader kicker={kicker} title={view.title} description={description} />
            <WithSidebar>
                <PostGrid posts={view.posts} />
                <Pagination pagination={view.pagination} />
            </WithSidebar>
        </>
    );
}
