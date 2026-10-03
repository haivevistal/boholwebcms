import { useMemo, useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Card, Input, PageHeader } from '../../Components/ui';

export default function Hooks({ hooks, shortcodes, postTypes, taxonomies }) {
    const [q, setQ] = useState('');
    const filtered = useMemo(() => hooks.filter((h) => h.name.toLowerCase().includes(q.toLowerCase())), [hooks, q]);
    const jsHooks = window.CMS?.hooks;

    return (
        <AdminLayout title="Hooks Inspector">
            <PageHeader title="Hooks Inspector" description="Actions & filters with callbacks attached during this request (core, active plugins and the active theme). Hooks fired later in the request — e.g. front-end only ones — may not appear." />
            <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
                <Card title={`PHP hooks (${filtered.length})`} actions={<Input placeholder="Filter…" value={q} onChange={(e) => setQ(e.target.value)} className="w-56 py-1" />} bodyClassName="p-0">
                    <div className="max-h-[70vh] overflow-y-auto">
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {filtered.map((h) => (
                                    <tr key={h.name}>
                                        <td className="px-4 py-2 font-mono text-xs text-slate-800">{h.name}</td>
                                        <td className="px-4 py-2 text-right text-xs text-slate-500">{h.callbacks} callback{h.callbacks === 1 ? '' : 's'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
                <div className="space-y-6">
                    <Card title={`Shortcodes (${shortcodes.length})`}>
                        <div className="flex flex-wrap gap-1.5">
                            {shortcodes.map((s) => (
                                <Badge key={s} color="brand">[{s}]</Badge>
                            ))}
                        </div>
                    </Card>
                    <Card title="Post types">
                        <div className="flex flex-wrap gap-1.5">{postTypes.map((s) => <Badge key={s}>{s}</Badge>)}</div>
                    </Card>
                    <Card title="Taxonomies">
                        <div className="flex flex-wrap gap-1.5">{taxonomies.map((s) => <Badge key={s}>{s}</Badge>)}</div>
                    </Card>
                    <Card title="JavaScript API">
                        <p className="text-sm text-slate-600">
                            <code>window.CMS</code> is available in the console. JS hooks fired so far: <strong>{jsHooks ? jsHooks.didAction('admin.init') : 0}</strong> × <code>admin.init</code>.
                        </p>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
