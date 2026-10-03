import { Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import Icon from '../../Components/Icon';
import { PageHeader } from '../../Components/ui';

export default function ToolsIndex({ tools }) {
    return (
        <AdminLayout title="Available Tools">
            <PageHeader title="Available Tools" description="Tools registered by the core and by plugins (register_tool())." />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {tools.map((tool) => (
                    <Link key={tool.slug} href={tool.url} className="group flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow">
                        <span className="grid size-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white">
                            <Icon name={tool.icon} className="size-5" />
                        </span>
                        <span>
                            <span className="block font-semibold text-slate-900">{tool.title}</span>
                            <span className="mt-1 block text-sm text-slate-600">{tool.description}</span>
                            <span className="mt-3 inline-block text-sm font-medium text-brand-600">{tool.action_label} →</span>
                        </span>
                    </Link>
                ))}
            </div>
        </AdminLayout>
    );
}
