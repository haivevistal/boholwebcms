import { Pagination, SearchForm } from '@boholwebcms/theme';
import { PageHeader, PostGrid, WithSidebar } from '../components';

export default function Search({ view }) {
    return (
        <>
            <PageHeader kicker="Search" title={view.title} description={`${view.pagination?.total ?? 0} result(s)`} />
            <WithSidebar>
                <div className="search-again">
                    <SearchForm />
                </div>
                <PostGrid posts={view.posts} />
                <Pagination pagination={view.pagination} />
            </WithSidebar>
        </>
    );
}
