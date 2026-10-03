import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ShieldCheck } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, Checkbox, cx, Input, Label, Modal, Notice, PageHeader, Select } from '../../Components/ui';

export default function Roles({ roles, capabilityGroups }) {
    const [activeSlug, setActiveSlug] = useState(roles[0]?.slug);
    const [creating, setCreating] = useState(false);
    const role = roles.find((r) => r.slug === activeSlug) || roles[0];

    return (
        <AdminLayout title="Roles">
            <PageHeader
                title="Roles & Capabilities"
                description="Roles decide what users can do. Plugins can register new capabilities (they appear below automatically) and new post types get their own edit/publish/delete capabilities."
                actions={
                    <Button variant="secondary" size="sm" onClick={() => setCreating(true)}>
                        Add Role
                    </Button>
                }
            />
            <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
                <Card bodyClassName="p-2">
                    <nav className="space-y-0.5">
                        {roles.map((r) => (
                            <button
                                key={r.slug}
                                type="button"
                                onClick={() => setActiveSlug(r.slug)}
                                className={cx('flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm', r.slug === role?.slug ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-700 hover:bg-slate-50')}
                            >
                                <span className="flex items-center gap-2">
                                    <ShieldCheck className="size-4 opacity-60" /> {r.name}
                                </span>
                                <span className="text-xs text-slate-500">{r.users}</span>
                            </button>
                        ))}
                    </nav>
                </Card>
                {role && <RoleEditor key={role.slug} role={role} groups={capabilityGroups} />}
            </div>
            {creating && <CreateRole roles={roles} onClose={() => setCreating(false)} />}
        </AdminLayout>
    );
}

function RoleEditor({ role, groups }) {
    const form = useForm({ name: role.name, capabilities: role.capabilities });
    const isAdmin = role.slug === 'administrator';
    const caps = new Set(form.data.capabilities);
    const toggle = (cap, on) => form.setData('capabilities', on ? [...caps, cap] : [...caps].filter((c) => c !== cap));
    const allKnown = Object.values(groups).flat();
    const unknown = [...caps].filter((c) => c !== '*' && !allKnown.includes(c));

    return (
        <Card
            title={
                <span className="flex items-center gap-2">
                    {role.name} <code className="text-xs font-normal text-slate-500">{role.slug}</code> {role.is_core && <Badge>Built-in</Badge>}
                </span>
            }
            actions={
                !role.is_core && (
                    <Button variant="danger-ghost" size="sm" onClick={() => window.confirm(`Delete the ${role.name} role? Its users move to the default role.`) && router.delete(`/admin/roles/${role.id}`)}>
                        Delete role
                    </Button>
                )
            }
            footer={
                <div className="flex justify-end">
                    <Button onClick={() => form.put(`/admin/roles/${role.id}`, { preserveScroll: true })} loading={form.processing}>
                        Save Role
                    </Button>
                </div>
            }
        >
            <div className="mb-5 max-w-sm">
                <Label>Display name</Label>
                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </div>
            {isAdmin ? (
                <Notice type="info">Administrators always have every capability (including ones added by plugins), so nobody can lock themselves out.</Notice>
            ) : (
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(groups).map(([group, list]) => (
                        <fieldset key={group}>
                            <legend className="mb-2 flex w-full items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {group}
                                <button type="button" className="font-normal normal-case text-brand-600 hover:underline" onClick={() => form.setData('capabilities', [...new Set([...caps, ...list])])}>
                                    all
                                </button>
                            </legend>
                            <div className="space-y-1.5">
                                {list.map((cap) => (
                                    <Checkbox key={cap} label={<code className="text-xs">{cap}</code>} checked={caps.has(cap)} onChange={(e) => toggle(cap, e.target.checked)} />
                                ))}
                            </div>
                        </fieldset>
                    ))}
                    {unknown.length > 0 && (
                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Other</legend>
                            {unknown.map((cap) => (
                                <Checkbox key={cap} label={<code className="text-xs">{cap}</code>} checked onChange={(e) => toggle(cap, e.target.checked)} />
                            ))}
                        </fieldset>
                    )}
                </div>
            )}
        </Card>
    );
}

function CreateRole({ roles, onClose }) {
    const form = useForm({ name: '', slug: '', copy_from: 'subscriber' });
    return (
        <Modal
            open
            onClose={onClose}
            title="Add Role"
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button onClick={() => form.post('/admin/roles', { onSuccess: onClose })} loading={form.processing}>
                        Create
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <Label>Name</Label>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Shop Manager" />
                    {form.errors.name && <p className="text-xs text-red-600">{form.errors.name}</p>}
                </div>
                <div>
                    <Label>Slug (optional)</Label>
                    <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="shop_manager" />
                    {form.errors.slug && <p className="text-xs text-red-600">{form.errors.slug}</p>}
                </div>
                <div>
                    <Label>Copy capabilities from</Label>
                    <Select value={form.data.copy_from} onChange={(e) => form.setData('copy_from', e.target.value)} options={roles.map((r) => ({ value: r.slug, label: r.name }))} />
                </div>
            </div>
        </Modal>
    );
}
