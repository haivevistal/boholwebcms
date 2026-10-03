import { Link } from '@inertiajs/react';
import { SearchForm, useTheme } from '@boholwebcms/theme';

export default function NotFound() {
    const { site } = useTheme();
    return (
        <section className="container not-found">
            <p className="not-found__code">404</p>
            <h1>We couldn’t find that page</h1>
            <p>It may have moved, or the link might be broken. Try searching instead.</p>
            <SearchForm />
            <p>
                <Link href={site.url} className="button">
                    Back to the homepage
                </Link>
            </p>
        </section>
    );
}
