import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Tags } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, EmptyState, FieldError, Input, Label, Modal, SearchBox, Select, Textarea } from '../../Components/ui';

export default function TermsIndex({ taxonomy, terms, parents, postType, defaultTermId, filters }) {
    const base = `/admin/terms/${taxonomy.name}`;
    const [selected, setSelected] = useState([]);
    const [editing, setEditing] = useState(null);
    const form = useForm({ name: '', slug: '', parent_id: '', description: '' });

    const parentOptions = (excludeId) => [{ value: '', label: 'None' }, ...flatten(parents, null, 0, excludeId)];

    return (
        <AdminLayout title={taxonomy.label}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">{taxonomy.label}</h1>
                <SearchBox value={filters.s} onSearch={(s) => router.get(base, { s, post_type: postType?.name }, { preserveState: true })} placeholder={taxonomy.labels.search_items} />
            </div>

            <div className="grid gap-6 lg:grid-cols-[340px_1fr]">
                <Card title={taxonomy.labels.add_new_item}>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({ ...d, parent_id: d.parent_id || null }));
                            form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <div>
                            <Label>Name</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                            <FieldError message={form.errors.name} />
                            <p className="mt-1 text-xs text-slate-500">The name is how it appears on your site.</p>
                        </div>
                        <div>
                            <Label>Slug</Label>
                            <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                            <p className="mt-1 text-xs text-slate-500">The URL-friendly version of the name.</p>
                        </div>
                        {taxonomy.hierarchical && (
                            <div>
                                <Label>Parent {taxonomy.labels.singular_name}</Label>
                                <Select value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)} options={parentOptions()} />
                            </div>
                        )}
                        <div>
                            <Label>Description</Label>
                            <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={3} />
                        </div>
                        <Button type="submit" loading={form.processing}>
                            {taxonomy.labels.add_new_item}
                        </Button>
                    </form>
                </Card>

                <div>
                    <div className="mb-3 flex items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            disabled={!selected.length}
                            onClick={() => window.confirm('Delete the selected items?') && router.post(`${base}/bulk`, { action: 'delete', ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) })}
                        >
                            Delete selected
                        </Button>
                        <span className="text-sm text-slate-500">{terms.length} items</span>
                    </div>
                    <Card bodyClassName="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th className="w-10 px-4 py-2.5">
                                        <input type="checkbox" className="rounded border-slate-300" checked={terms.length > 0 && selected.length === terms.length} onChange={(e) => setSelected(e.target.checked ? terms.map((t) => t.id) : [])} />
                                    </th>
                                    <th className="px-3 py-2.5 font-medium">Name</th>
                                    <th className="px-3 py-2.5 font-medium">Description</th>
                                    <th className="px-3 py-2.5 font-medium">Slug</th>
                                    <th className="px-3 py-2.5 text-right font-medium">Count</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {terms.map((term) => (
                                    <tr key={term.id} className="group hover:bg-slate-50/70">
                                        <td className="px-4 py-3 align-top">
                                            {term.id !== defaultTermId && (
                                                <input
                                                    type="checkbox"
                                                    className="rounded border-slate-300"
                                                    checked={selected.includes(term.id)}
                                                    onChange={(e) => setSelected((s) => (e.target.checked ? [...s, term.id] : s.filter((x) => x !== term.id)))}
                                                />
                                            )}
                                        </td>
                                        <td className="px-3 py-3 align-top">
                                            <button type="button" className="font-semibold text-brand-700 hover:underline" onClick={() => setEditing(term)}>
                                                {'— '.repeat(term.depth)}
                                                {term.name}
                                            </button>
                                            {term.id === defaultTermId && <Badge className="ml-2">Default</Badge>}
                                            <div className="mt-1 flex gap-2 text-xs md:opacity-0 md:group-hover:opacity-100">
                                                <button className="text-brand-600 hover:underline" onClick={() => setEditing(term)}>
                                                    Edit
                                                </button>
                                                {term.id !== defaultTermId && (
                                                    <>
                                                        <span className="text-slate-300">|</span>
                                                        <button className="text-red-600 hover:underline" onClick={() => window.confirm(`Delete “${term.name}”?`) && router.delete(`${base}/${term.id}`, { preserveScroll: true })}>
                                                            Delete
                                                        </button>
                                                    </>
                                                )}
                                                <span className="text-slate-300">|</span>
                                                <a href={term.link} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline">
                                                    View
                                                </a>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 align-top text-slate-600">{term.description || <span className="text-slate-400">—</span>}</td>
                                        <td className="px-3 py-3 align-top font-mono text-xs text-slate-600">{term.slug}</td>
                                        <td className="px-3 py-3 text-right align-top">
                                            {postType ? (
                                                <Link href={`/admin/content/${postType.name}?term=${term.id}`} className="text-brand-600 hover:underline">
                                                    {term.count}
                                                </Link>
                                            ) : (
                                                term.count
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!terms.length && <EmptyState icon={Tags} title={taxonomy.labels.not_found} />}
                    </Card>
                </div>
            </div>

            {editing && <EditTerm term={editing} taxonomy={taxonomy} base={base} parentOptions={parentOptions(editing.id)} onClose={() => setEditing(null)} />}
        </AdminLayout>
    );
}

function EditTerm({ term, taxonomy, base, parentOptions, onClose }) {
    const form = useForm({ name: term.name, slug: term.slug, parent_id: term.parent_id || '', description: term.description || '' });
    const submit = () => {
        form.transform((d) => ({ ...d, parent_id: d.parent_id || null }));
        form.put(`${base}/${term.id}`, { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal
            open
            onClose={onClose}
            title={taxonomy.labels.edit_item}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} loading={form.processing}>
                        Update
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <Label>Name</Label>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <FieldError message={form.errors.name} />
                </div>
                <div>
                    <Label>Slug</Label>
                    <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                </div>
                {taxonomy.hierarchical && (
                    <div>
                        <Label>Parent</Label>
                        <Select value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)} options={parentOptions} />
                    </div>
                )}
                <div>
                    <Label>Description</Label>
                    <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </div>
            </div>
        </Modal>
    );
}

function flatten(items, parent, depth, excludeId) {
    return items
        .filter((t) => (t.parent_id ?? null) === parent && t.id !== excludeId)
        .flatMap((t) => [{ value: t.id, label: `${'— '.repeat(depth)}${t.name}` }, ...flatten(items, t.id, depth + 1, excludeId)]);
}
