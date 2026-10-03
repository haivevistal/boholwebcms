import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plug } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, cx, EmptyState, Notice, SearchBox, Select, StatusTabs } from '../../Components/ui';
import HtmlContent from '../../../shared/HtmlContent';

export default function PluginsIndex({ plugins, counts, filters, can }) {
    const [selected, setSelected] = useState([]);
    const [bulk, setBulk] = useState('');
    const query = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');

    const tabs = [
        { key: 'all', label: 'All', count: counts.all },
        { key: 'active', label: 'Active', count: counts.active },
        { key: 'inactive', label: 'Inactive', count: counts.inactive },
    ];

    const act = (url, key, confirmMsg) => {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        router.post(url, { plugin: key }, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Plugins">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Plugins</h1>
                {can.install && (
                    <Button href="/admin/plugins/add" variant="secondary" size="sm">
                        Add New Plugin
                    </Button>
                )}
            </div>

            {query.get('activated') && <Notice type="success" className="mb-4">Plugin activated.</Notice>}
            {query.get('deactivated') && <Notice type="success" className="mb-4">Plugin deactivated.</Notice>}

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <StatusTabs tabs={tabs} active={filters.status || 'all'} buildHref={(key) => `/admin/plugins?status=${key}`} />
                <SearchBox value={filters.s} onSearch={(s) => router.get('/admin/plugins', { ...filters, s }, { preserveState: true })} placeholder="Search installed plugins" />
            </div>

            <div className="mb-3 flex items-center gap-2">
                <Select value={bulk} onChange={(e) => setBulk(e.target.value)} className="w-44" placeholder="Bulk actions" options={{ activate: 'Activate', deactivate: 'Deactivate', ...(can.delete ? { delete: 'Delete' } : {}) }} />
                <Button
                    variant="secondary"
                    disabled={!bulk || !selected.length}
                    onClick={() => (bulk !== 'delete' || window.confirm('Delete the selected plugins and their data?')) && router.post('/admin/plugins/bulk', { action: bulk, plugins: selected })}
                >
                    Apply
                </Button>
            </div>

            <Card bodyClassName="p-0">
                <table className="w-full text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="w-10 px-4 py-2.5">
                                <input type="checkbox" className="rounded border-slate-300" checked={plugins.length > 0 && selected.length === plugins.length} onChange={(e) => setSelected(e.target.checked ? plugins.map((p) => p.key) : [])} />
                            </th>
                            <th className="w-72 px-3 py-2.5 font-medium">Plugin</th>
                            <th className="px-3 py-2.5 font-medium">Description</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {plugins.map((p) => (
                            <tr key={p.key} className={cx('align-top', p.active ? 'bg-brand-50/40' : '')}>
                                <td className={cx('px-4 py-3', p.active && 'border-l-4 border-brand-500 pl-3')}>
                                    <input type="checkbox" className="rounded border-slate-300" checked={selected.includes(p.key)} onChange={(e) => setSelected((s) => (e.target.checked ? [...s, p.key] : s.filter((x) => x !== p.key)))} />
                                </td>
                                <td className="px-3 py-3">
                                    <p className="font-semibold text-slate-900">{p.name}</p>
                                    <div className="mt-1 flex flex-wrap gap-x-2 text-xs">
                                        {p.active ? (
                                            <button className="text-brand-600 hover:underline" onClick={() => act('/admin/plugins/deactivate', p.key)}>
                                                Deactivate
                                            </button>
                                        ) : (
                                            <button className="font-medium text-brand-600 hover:underline" onClick={() => act('/admin/plugins/activate', p.key)}>
                                                Activate
                                            </button>
                                        )}
                                        {can.edit && (
                                            <>
                                                <span className="text-slate-300">|</span>
                                                <Link href={`/admin/plugin-editor?plugin=${encodeURIComponent(p.key)}`} className="text-brand-600 hover:underline">
                                                    Edit
                                                </Link>
                                            </>
                                        )}
                                        {!p.active && can.delete && (
                                            <>
                                                <span className="text-slate-300">|</span>
                                                <button className="text-red-600 hover:underline" onClick={() => act('/admin/plugins/delete', p.key, `Delete ${p.name}? Its files and (if it has an uninstall routine) its data will be removed.`)}>
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                        {Object.entries(p.action_links || {}).map(([k, html]) => (
                                            <span key={k} className="flex gap-2">
                                                <span className="text-slate-300">|</span>
                                                <HtmlContent as="span" html={html} className="pm-html" />
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-3 py-3 text-slate-700">
                                    <p>{p.description}</p>
                                    <p className="mt-1.5 text-xs text-slate-500">
                                        Version {p.version || '—'}
                                        {p.author && (
                                            <>
                                                {' '}
                                                | By {p.author_uri ? <a href={p.author_uri} className="text-brand-600 hover:underline" target="_blank" rel="noreferrer">{p.author}</a> : p.author}
                                            </>
                                        )}
                                        {p.uri && (
                                            <>
                                                {' '}
                                                | <a href={p.uri} className="text-brand-600 hover:underline" target="_blank" rel="noreferrer">Visit plugin site</a>
                                            </>
                                        )}
                                        {p.requires_plugins?.length > 0 && <> | Requires: {p.requires_plugins.join(', ')}</>}
                                    </p>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!plugins.length && (
                    <EmptyState icon={Plug} title="No plugins found.">
                        Upload a plugin .zip from <Link href="/admin/plugins/add" className="text-brand-600 hover:underline">Add New Plugin</Link>.
                    </EmptyState>
                )}
            </Card>
        </AdminLayout>
    );
}
