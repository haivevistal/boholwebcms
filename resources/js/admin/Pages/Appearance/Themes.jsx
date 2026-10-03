import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, LayoutTemplate, Upload } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, Checkbox, cx, Modal } from '../../Components/ui';

export default function Themes({ themes, can }) {
    const [showUpload, setShowUpload] = useState(false);
    const [details, setDetails] = useState(null);
    const active = themes.find((t) => t.active);
    const sorted = [...themes].sort((a, b) => (b.active ? 1 : 0) - (a.active ? 1 : 0));

    return (
        <AdminLayout title="Themes">
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Themes</h1>
                <Badge>{themes.length}</Badge>
                {can.install && (
                    <Button variant="secondary" size="sm" icon={Upload} onClick={() => setShowUpload((s) => !s)}>
                        Add New Theme
                    </Button>
                )}
            </div>

            {showUpload && <UploadBox />}

            <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                {sorted.map((theme) => (
                    <article key={theme.slug} className={cx('group overflow-hidden rounded-xl border bg-white shadow-sm', theme.active ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-slate-200')}>
                        <button type="button" onClick={() => setDetails(theme)} className="relative block aspect-[4/3] w-full overflow-hidden bg-slate-100">
                            {theme.screenshot ? (
                                <img src={theme.screenshot} alt="" className="size-full object-cover object-top transition-transform duration-500 group-hover:scale-[1.02]" />
                            ) : (
                                <span className="grid size-full place-items-center text-slate-300">
                                    <LayoutTemplate className="size-12" />
                                </span>
                            )}
                            <span className="absolute inset-0 grid place-items-center bg-slate-900/50 text-sm font-medium text-white opacity-0 transition group-hover:opacity-100">Theme Details</span>
                        </button>
                        <div className={cx('flex items-center justify-between gap-2 px-4 py-3', theme.active && 'bg-slate-900 text-white')}>
                            <div className="min-w-0">
                                <h2 className="truncate font-semibold">
                                    {theme.active && <span className="font-normal opacity-70">Active: </span>}
                                    {theme.name}
                                </h2>
                                {!theme.built && (
                                    <p className="flex items-center gap-1 text-xs text-amber-600">
                                        <AlertTriangle className="size-3" /> Bundle not built
                                    </p>
                                )}
                            </div>
                            {theme.active ? (
                                can.customize && (
                                    <Button size="sm" href="/admin/customize">
                                        Customize
                                    </Button>
                                )
                            ) : (
                                <div className="flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => router.post(`/admin/themes/${theme.slug}/activate`, {}, { preserveScroll: true })}>
                                        Activate
                                    </Button>
                                </div>
                            )}
                        </div>
                    </article>
                ))}
            </div>

            {details && (
                <Modal
                    open
                    onClose={() => setDetails(null)}
                    size="lg"
                    title={details.name}
                    footer={
                        <>
                            {!details.active && can.delete && (
                                <Button
                                    variant="danger-ghost"
                                    className="mr-auto"
                                    onClick={() => window.confirm(`Delete the ${details.name} theme? This removes its files.`) && router.delete(`/admin/themes/${details.slug}`, { onSuccess: () => setDetails(null) })}
                                >
                                    Delete
                                </Button>
                            )}
                            {details.active ? (
                                <Button href="/admin/customize">Customize</Button>
                            ) : (
                                <Button onClick={() => router.post(`/admin/themes/${details.slug}/activate`, {}, { onSuccess: () => setDetails(null) })}>Activate</Button>
                            )}
                        </>
                    }
                >
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="overflow-hidden rounded-lg border border-slate-200 bg-slate-100">{details.screenshot && <img src={details.screenshot} alt="" className="w-full" />}</div>
                        <div className="space-y-3 text-sm">
                            <p className="text-slate-500">
                                Version {details.version || '—'}
                                {details.author && (
                                    <>
                                        {' '}
                                        · By{' '}
                                        {details.author_uri ? (
                                            <a href={details.author_uri} className="text-brand-600 hover:underline" target="_blank" rel="noreferrer">
                                                {details.author}
                                            </a>
                                        ) : (
                                            details.author
                                        )}
                                    </>
                                )}
                            </p>
                            {details.parent && <p className="rounded bg-sky-50 px-3 py-2 text-sky-800">This is a child theme of <strong>{details.parent}</strong>.</p>}
                            <p className="leading-relaxed text-slate-700">{details.description}</p>
                            {details.tags?.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {details.tags.map((t) => (
                                        <Badge key={t}>{t}</Badge>
                                    ))}
                                </div>
                            )}
                            {!details.built && (
                                <p className="rounded bg-amber-50 px-3 py-2 text-amber-800">
                                    The theme’s JavaScript bundle is missing. Build it with <code>npm run build:themes</code> — the site falls back to a basic built-in template meanwhile.
                                </p>
                            )}
                        </div>
                    </div>
                </Modal>
            )}
            {active && <span className="sr-only">Active theme: {active.name}</span>}
        </AdminLayout>
    );
}

function UploadBox() {
    const form = useForm({ package: null, overwrite: false });
    return (
        <Card className="mb-6" title="Upload Theme">
            <p className="mb-3 text-sm text-slate-600">If you have a theme in a .zip format, you may install it by uploading it here.</p>
            <form
                className="flex flex-wrap items-center gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/themes/upload', { forceFormData: true, onSuccess: () => form.reset() });
                }}
            >
                <input type="file" accept=".zip" onChange={(e) => form.setData('package', e.target.files[0])} className="text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm" />
                <Checkbox label="Replace if it already exists" checked={form.data.overwrite} onChange={(e) => form.setData('overwrite', e.target.checked)} />
                <Button type="submit" disabled={!form.data.package} loading={form.processing}>
                    Install Now
                </Button>
            </form>
            {form.errors.package && <p className="mt-2 text-xs text-red-600">{form.errors.package}</p>}
        </Card>
    );
}
