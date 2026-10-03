import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, Checkbox, FieldError, PageHeader } from '../../Components/ui';

export default function Import({ importers }) {
    const form = useForm({ file: null, import_settings: false });

    return (
        <AdminLayout title="Import">
            <PageHeader title="Import" description="Import posts, pages, custom post types, terms and comments from a BoholwebCMS export file. Items whose slug already exists are skipped." />
            <div className="grid max-w-5xl gap-6 lg:grid-cols-2">
                <Card title="BoholwebCMS (JSON)">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/admin/tools/import', { forceFormData: true });
                        }}
                    >
                        <input type="file" accept=".json,application/json" onChange={(e) => form.setData('file', e.target.files[0])} className="text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2" />
                        <FieldError message={form.errors.file} />
                        <Checkbox label="Also import settings (overwrites current options)" checked={form.data.import_settings} onChange={(e) => form.setData('import_settings', e.target.checked)} />
                        <Button type="submit" disabled={!form.data.file} loading={form.processing}>
                            Upload file and import
                        </Button>
                    </form>
                </Card>
                {Object.entries(importers || {}).map(([key, imp]) => (
                    <Card key={key} title={imp.title}>
                        <p className="mb-3 text-sm text-slate-600">{imp.description}</p>
                        <Link href={imp.url} className="text-sm font-medium text-brand-600 hover:underline">
                            Run importer →
                        </Link>
                    </Card>
                ))}
            </div>
        </AdminLayout>
    );
}
