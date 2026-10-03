import { Head, usePage } from '@inertiajs/react';

export default function AuthShell({ title, children, footer }) {
    const { site, cms } = usePage().props;
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-100 via-white to-brand-50 px-4 py-12">
            <Head title={`${title} ‹ ${site?.name ?? cms?.name}`} />
            <a href={site?.url || '/'} className="mb-6 flex items-center gap-2 text-slate-800">
                <span className="grid size-10 place-items-center rounded-lg bg-brand-600 text-lg font-bold text-white shadow">{(cms?.name || 'P')[0]}</span>
                <span className="text-xl font-semibold">{site?.name || cms?.name}</span>
            </a>
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">{children}</div>
            {footer && <div className="mt-4 text-center text-sm text-slate-600">{footer}</div>}
        </div>
    );
}
