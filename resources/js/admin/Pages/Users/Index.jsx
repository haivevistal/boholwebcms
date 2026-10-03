import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Users } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, EmptyState, Pagination, SearchBox, Select, StatusTabs } from '../../Components/ui';
import HtmlContent from '../../../shared/HtmlContent';

export default function UsersIndex({ users, roles, counts, total, filters, extraColumns }) {
    const [selected, setSelected] = useState([]);
    const [bulk, setBulk] = useState('');
    const [role, setRole] = useState('');

    const tabs = [{ key: '', label: 'All', count: total }, ...roles.filter((r) => counts[r.slug]).map((r) => ({ key: r.slug, label: r.name, count: counts[r.slug] }))];

    const sortLink = (key, label) => (
        <button type="button" onClick={() => router.get('/admin/users', { ...filters, orderby: key, order: filters.orderby === key && filters.order !== 'desc' ? 'desc' : 'asc' }, { preserveState: true })} className="hover:text-slate-900">
            {label} {filters.orderby === key ? (filters.order === 'desc' ? '▼' : '▲') : ''}
        </button>
    );

    return (
        <AdminLayout title="Users">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Users</h1>
                <Button href="/admin/users/create" variant="secondary" size="sm">
                    Add User
                </Button>
            </div>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <StatusTabs tabs={tabs} active={filters.role || ''} buildHref={(key) => (key ? `/admin/users?role=${key}` : '/admin/users')} />
                <SearchBox value={filters.s} onSearch={(s) => router.get('/admin/users', { ...filters, s }, { preserveState: true })} placeholder="Search users" />
            </div>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Select value={bulk} onChange={(e) => setBulk(e.target.value)} className="w-40" placeholder="Bulk actions" options={{ delete: 'Delete' }} />
                <Button
                    variant="secondary"
                    disabled={!bulk || !selected.length}
                    onClick={() => window.confirm('Delete the selected users? Their content will be moved to the trash.') && router.post('/admin/users/bulk', { action: 'delete', ids: selected }, { onSuccess: () => setSelected([]) })}
                >
                    Apply
                </Button>
                <span className="mx-2 text-slate-300">|</span>
                <Select value={role} onChange={(e) => setRole(e.target.value)} className="w-44" placeholder="Change role to…" options={roles.map((r) => ({ value: r.slug, label: r.name }))} />
                <Button variant="secondary" disabled={!role || !selected.length} onClick={() => router.post('/admin/users/bulk', { action: 'role', role, ids: selected }, { onSuccess: () => setSelected([]) })}>
                    Change
                </Button>
            </div>

            <Card bodyClassName="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th className="w-10 px-4 py-2.5">
                                    <input type="checkbox" className="rounded border-slate-300" onChange={(e) => setSelected(e.target.checked ? users.data.filter((u) => !u.is_self).map((u) => u.id) : [])} />
                                </th>
                                <th className="px-3 py-2.5 font-medium">{sortLink('username', 'Username')}</th>
                                <th className="px-3 py-2.5 font-medium">{sortLink('name', 'Name')}</th>
                                <th className="px-3 py-2.5 font-medium">{sortLink('email', 'Email')}</th>
                                <th className="px-3 py-2.5 font-medium">Role</th>
                                <th className="px-3 py-2.5 text-right font-medium">Posts</th>
                                {Object.entries(extraColumns || {}).map(([k, label]) => (
                                    <th key={k} className="px-3 py-2.5 font-medium">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.map((u) => (
                                <tr key={u.id} className="group hover:bg-slate-50/70">
                                    <td className="px-4 py-3">
                                        {!u.is_self && <input type="checkbox" className="rounded border-slate-300" checked={selected.includes(u.id)} onChange={(e) => setSelected((s) => (e.target.checked ? [...s, u.id] : s.filter((x) => x !== u.id)))} />}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center gap-3">
                                            <img src={u.avatar} alt="" className="size-8 rounded-full bg-slate-200" />
                                            <div>
                                                <Link href={u.is_self ? '/admin/profile' : `/admin/users/${u.id}/edit`} className="font-semibold text-brand-700 hover:underline">
                                                    {u.username}
                                                </Link>
                                                <div className="flex gap-2 text-xs md:opacity-0 md:group-hover:opacity-100">
                                                    <Link href={u.is_self ? '/admin/profile' : `/admin/users/${u.id}/edit`} className="text-brand-600 hover:underline">
                                                        Edit
                                                    </Link>
                                                    {!u.is_self && (
                                                        <button className="text-red-600 hover:underline" onClick={() => window.confirm(`Delete ${u.username}? Their posts will be moved to the trash.`) && router.delete(`/admin/users/${u.id}`)}>
                                                            Delete
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-3 py-3 text-slate-700">{u.name}</td>
                                    <td className="px-3 py-3">
                                        <a href={`mailto:${u.email}`} className="text-brand-600 hover:underline">
                                            {u.email}
                                        </a>
                                    </td>
                                    <td className="px-3 py-3 text-slate-700">{u.role_name}</td>
                                    <td className="px-3 py-3 text-right">{u.posts_count}</td>
                                    {Object.keys(extraColumns || {}).map((k) => (
                                        <td key={k} className="px-3 py-3">
                                            <HtmlContent html={u.custom?.[k] ?? ''} className="pm-html" />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!users.data.length && <EmptyState icon={Users} title="No users found." />}
            </Card>
            <div className="mt-4">
                <Pagination meta={users} />
            </div>
        </AdminLayout>
    );
}
