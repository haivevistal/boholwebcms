import { router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, PageHeader } from '../../Components/ui';

export default function ClearCache() {
    return (
        <AdminLayout title="Clear Cache">
            <PageHeader title="Clear Cache" />
            <Card className="max-w-xl">
                <p className="mb-4 text-sm text-slate-600">Clears the application cache plus compiled views, routes and configuration. Plugins can hook <code>cms_cache_cleared</code> to flush their own caches.</p>
                <Button onClick={() => router.post('/admin/tools/clear-cache')}>Clear all caches</Button>
            </Card>
        </AdminLayout>
    );
}
