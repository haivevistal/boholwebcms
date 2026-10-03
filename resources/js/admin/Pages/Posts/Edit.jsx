import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, ExternalLink, ImageIcon, Plus, Trash2, X } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import RichEditor from '../../Components/RichEditor';
import MediaPicker from '../../Components/MediaPicker';
import FieldRenderer from '../../Components/FieldRenderer';
import { Button, Checkbox, cx, FieldError, Input, Select, Textarea } from '../../Components/ui';
import HtmlContent from '../../../shared/HtmlContent';

export default function PostEdit(props) {
    const { postType, post, permalinkBase, taxonomies, metaBoxes, customFields, pageTemplates, parents, authors, editorMode, shortcodes, can } = props;
    const supports = (f) => postType.supports.includes(f);
    const isNew = !post.id;
    const formRef = useRef(null);

    const initialMeta = useMemo(() => Object.assign({}, ...metaBoxes.map((b) => b.values)), [metaBoxes]);

    const form = useForm({
        title: post.title || '',
        slug: post.slug || '',
        content: post.content || '',
        excerpt: post.excerpt || '',
        status: post.status || 'draft',
        published_at: post.published_at || '',
        parent_id: post.parent_id || '',
        menu_order: post.menu_order ?? 0,
        template: post.template || '',
        comment_status: post.comment_status || 'closed',
        featured_media_id: post.featured_media?.id || null,
        password: post.password || '',
        author_id: post.author_id || '',
        terms: Object.fromEntries(taxonomies.map((t) => [t.name, t.selected || []])),
        meta: initialMeta,
        custom_fields: customFields,
        meta_box: {},
    });

    const [visibility, setVisibility] = useState(post.status === 'private' ? 'private' : post.password ? 'password' : 'public');
    const [featured, setFeatured] = useState(post.featured_media);
    const [mediaOpen, setMediaOpen] = useState(false);
    const [editingSlug, setEditingSlug] = useState(false);

    // Warn about unsaved changes.
    useEffect(() => {
        const handler = (e) => {
            if (form.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [form.isDirty]);

    // Ctrl/Cmd+S saves.
    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                save(null);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    const isPublished = ['publish', 'private', 'future'].includes(post.status);

    const save = (status) => {
        form.transform((data) => ({
            ...data,
            status: status ?? data.status,
            password: visibility === 'password' ? data.password : '',
            meta_box: collectHtmlInputs(formRef.current),
            parent_id: data.parent_id || null,
            author_id: data.author_id || null,
        }));
        const opts = { preserveScroll: true };
        if (isNew) form.post(`/admin/content/${postType.name}`, opts);
        else form.put(`/admin/content/${postType.name}/${post.id}`, opts);
    };

    const publishStatus = visibility === 'private' ? 'private' : 'publish';
    const sidebarExtras = window.CMS.hooks.applyFilters('admin.editor.sidebar', [], { post, postType, form });
    const mainExtras = window.CMS.hooks.applyFilters('admin.editor.main', [], { post, postType, form });

    return (
        <AdminLayout title={isNew ? postType.labels.add_new_item : postType.labels.edit_item}>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">{isNew ? postType.labels.add_new_item : postType.labels.edit_item}</h1>
                {!isNew && (
                    <Button href={`/admin/content/${postType.name}/create`} variant="secondary" size="sm">
                        {postType.labels.add_new}
                    </Button>
                )}
            </div>

            <form
                ref={formRef}
                onSubmit={(e) => {
                    e.preventDefault();
                    save(null);
                }}
                className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
            >
                {/* ---------------- Main column ---------------- */}
                <div className="min-w-0 space-y-5">
                    {supports('title') && (
                        <div>
                            <input
                                className="block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-xl font-semibold shadow-sm placeholder:font-normal placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                                placeholder="Add title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                autoFocus={isNew}
                            />
                            <FieldError message={form.errors.title} />
                            {permalinkBase && (
                                <div className="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                                    <strong>Permalink:</strong>
                                    {editingSlug ? (
                                        <>
                                            <span className="text-slate-500">{permalinkBase.replace('%postname%', '')}</span>
                                            <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} className="w-56 py-1 text-xs" />
                                            <Button size="xs" variant="secondary" onClick={() => setEditingSlug(false)}>
                                                OK
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            {post.permalink && post.status === 'publish' ? (
                                                <a href={post.permalink} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline">
                                                    {permalinkBase.replace('%postname%', form.data.slug || slugify(form.data.title))}
                                                </a>
                                            ) : (
                                                <span>{permalinkBase.replace('%postname%', form.data.slug || slugify(form.data.title) || '…')}</span>
                                            )}
                                            <Button size="xs" variant="secondary" onClick={() => setEditingSlug(true)}>
                                                Edit
                                            </Button>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {supports('editor') && (
                        <div>
                            <RichEditor value={form.data.content} onChange={(v) => form.setData('content', v)} initialMode={editorMode} shortcodes={shortcodes} />
                            <FieldError message={form.errors.content} />
                        </div>
                    )}

                    {mainExtras}

                    <div className="space-y-5">
                        {metaBoxes
                            .filter((b) => b.context !== 'side')
                            .map((box) => (
                                <MetaBox key={box.id} box={box} form={form} />
                            ))}
                    </div>

                    {supports('excerpt') && (
                        <Box title="Excerpt" defaultOpen={!!post.excerpt}>
                            <Textarea value={form.data.excerpt} onChange={(e) => form.setData('excerpt', e.target.value)} rows={3} />
                            <p className="mt-1.5 text-xs text-slate-500">Excerpts are optional hand-crafted summaries of your content that can be used in your theme.</p>
                        </Box>
                    )}

                    {supports('custom-fields') && <CustomFields form={form} />}

                    {supports('comments') && (
                        <Box title="Discussion" defaultOpen={false}>
                            <Checkbox label="Allow comments" checked={form.data.comment_status === 'open'} onChange={(e) => form.setData('comment_status', e.target.checked ? 'open' : 'closed')} />
                        </Box>
                    )}
                </div>

                {/* ---------------- Sidebar ---------------- */}
                <aside className="space-y-4">
                    <Box title="Publish" defaultOpen>
                        <div className="mb-3 flex justify-between gap-2">
                            {!isPublished && (
                                <Button variant="secondary" size="sm" onClick={() => save(form.data.status === 'pending' ? 'pending' : 'draft')} loading={form.processing}>
                                    Save {form.data.status === 'pending' ? 'as Pending' : 'Draft'}
                                </Button>
                            )}
                            {post.id && (
                                <Button variant="secondary" size="sm" href={post.status === 'publish' ? post.permalink : post.preview_url} external target="_blank" rel="noreferrer" icon={ExternalLink}>
                                    {post.status === 'publish' ? 'View' : 'Preview'}
                                </Button>
                            )}
                        </div>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-slate-500">Status</dt>
                                <dd>
                                    <Select
                                        className="w-40 py-1 text-xs"
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        options={Object.fromEntries(
                                            Object.entries(props.statuses).filter(([k]) => k !== 'trash' && (can.publish || ['draft', 'pending'].includes(k) || k === post.status) && (k !== 'future' || post.status === 'future')),
                                        )}
                                    />
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-slate-500">Visibility</dt>
                                <dd>
                                    <Select
                                        className="w-40 py-1 text-xs"
                                        value={visibility}
                                        onChange={(e) => {
                                            setVisibility(e.target.value);
                                            if (e.target.value === 'private' && isPublished) form.setData('status', 'private');
                                            if (e.target.value !== 'private' && form.data.status === 'private') form.setData('status', 'publish');
                                        }}
                                        options={{ public: 'Public', password: 'Password protected', private: 'Private' }}
                                    />
                                </dd>
                            </div>
                            {visibility === 'password' && <Input placeholder="Password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className="py-1 text-xs" />}
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-slate-500">Publish</dt>
                                <dd>
                                    <Input type="datetime-local" className="w-48 py-1 text-xs" value={form.data.published_at || ''} onChange={(e) => form.setData('published_at', e.target.value)} />
                                </dd>
                            </div>
                            {authors.length > 0 && supports('author') && (
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-slate-500">Author</dt>
                                    <dd>
                                        <Select className="w-40 py-1 text-xs" value={form.data.author_id} onChange={(e) => form.setData('author_id', e.target.value)} options={authors.map((a) => ({ value: a.id, label: a.name }))} />
                                    </dd>
                                </div>
                            )}
                        </dl>
                        <div className="-mx-4 -mb-4 mt-4 flex items-center justify-between gap-2 rounded-b-lg border-t border-slate-200 bg-slate-50 px-4 py-3">
                            {can.delete ? (
                                <button
                                    type="button"
                                    className="text-xs text-red-600 hover:underline"
                                    onClick={() => router.post(`/admin/content/${postType.name}/${post.id}/trash`, {}, { onSuccess: () => router.visit(`/admin/content/${postType.name}`) })}
                                >
                                    Move to Trash
                                </button>
                            ) : (
                                <span />
                            )}
                            {isPublished ? (
                                <Button type="button" onClick={() => save(null)} loading={form.processing}>
                                    Update
                                </Button>
                            ) : can.publish ? (
                                <Button type="button" onClick={() => save(isFuture(form.data.published_at) ? 'future' : publishStatus)} loading={form.processing}>
                                    {isFuture(form.data.published_at) ? 'Schedule' : 'Publish'}
                                </Button>
                            ) : (
                                <Button type="button" onClick={() => save('pending')} loading={form.processing}>
                                    Submit for Review
                                </Button>
                            )}
                        </div>
                        {post.updated_at && !isNew && <p className="mt-6 text-center text-[11px] text-slate-400">{form.isDirty ? 'Unsaved changes' : 'All changes saved'}</p>}
                    </Box>

                    {sidebarExtras}

                    {supports('page-attributes') && (
                        <Box title="Page Attributes" defaultOpen>
                            <div className="space-y-3 text-sm">
                                {postType.hierarchical && (
                                    <label className="block">
                                        <span className="mb-1 block text-xs font-medium text-slate-600">Parent</span>
                                        <Select value={form.data.parent_id || ''} onChange={(e) => form.setData('parent_id', e.target.value)} options={[{ value: '', label: '(no parent)' }, ...parentOptions(parents)]} />
                                    </label>
                                )}
                                {Object.keys(pageTemplates).length > 0 && (
                                    <label className="block">
                                        <span className="mb-1 block text-xs font-medium text-slate-600">Template</span>
                                        <Select value={form.data.template || ''} onChange={(e) => form.setData('template', e.target.value)} options={{ '': 'Default template', ...pageTemplates }} />
                                    </label>
                                )}
                                <label className="block">
                                    <span className="mb-1 block text-xs font-medium text-slate-600">Order</span>
                                    <Input type="number" className="w-24" value={form.data.menu_order} onChange={(e) => form.setData('menu_order', e.target.value)} />
                                </label>
                            </div>
                        </Box>
                    )}

                    {taxonomies.map((tax) => (
                        <Box key={tax.name} title={tax.label} defaultOpen>
                            {tax.hierarchical ? <HierarchicalTerms tax={tax} form={form} /> : <TagInput tax={tax} form={form} />}
                        </Box>
                    ))}

                    {supports('thumbnail') && (
                        <Box title={postType.name === 'page' || postType.name === 'post' ? 'Featured Image' : 'Image'} defaultOpen>
                            {featured ? (
                                <div className="space-y-2">
                                    <button type="button" onClick={() => setMediaOpen(true)} className="block w-full overflow-hidden rounded border border-slate-200">
                                        <img src={featured.url || featured.medium} alt="" className="w-full" />
                                    </button>
                                    <button
                                        type="button"
                                        className="text-xs text-red-600 hover:underline"
                                        onClick={() => {
                                            setFeatured(null);
                                            form.setData('featured_media_id', null);
                                        }}
                                    >
                                        Remove featured image
                                    </button>
                                </div>
                            ) : (
                                <button type="button" onClick={() => setMediaOpen(true)} className="flex w-full flex-col items-center gap-2 rounded border-2 border-dashed border-slate-300 py-6 text-sm text-brand-600 hover:bg-slate-50">
                                    <ImageIcon className="size-6 text-slate-400" />
                                    Set featured image
                                </button>
                            )}
                            <MediaPicker
                                open={mediaOpen}
                                onClose={() => setMediaOpen(false)}
                                type="image"
                                title="Featured image"
                                onSelect={(m) => {
                                    setFeatured({ id: m.id, url: m.medium || m.url });
                                    form.setData('featured_media_id', m.id);
                                    setMediaOpen(false);
                                }}
                            />
                        </Box>
                    )}

                    <div className="space-y-4">
                        {metaBoxes
                            .filter((b) => b.context === 'side')
                            .map((box) => (
                                <MetaBox key={box.id} box={box} form={form} />
                            ))}
                    </div>
                </aside>
            </form>
        </AdminLayout>
    );
}

/* ------------------------------------------------------------------ */

function Box({ title, children, defaultOpen = true }) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <section className="rounded-lg border border-slate-200 bg-white shadow-sm">
            <button type="button" onClick={() => setOpen((o) => !o)} className="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm font-semibold text-slate-800">
                {title}
                <ChevronDown className={cx('size-4 text-slate-400 transition-transform', open && 'rotate-180')} />
            </button>
            {open && <div className="border-t border-slate-100 p-4">{children}</div>}
        </section>
    );
}

function MetaBox({ box, form }) {
    return (
        <Box title={box.title} defaultOpen>
            {box.fields.length > 0 && (
                <div className="space-y-4">
                    {box.fields.map((field) => (
                        <div key={field.name}>
                            {field.label && field.type !== 'checkbox' && <label className="mb-1 block text-sm font-medium text-slate-700">{field.label}</label>}
                            <FieldRenderer
                                field={field.type === 'checkbox' ? { ...field, description_inline: field.label } : field}
                                value={form.data.meta[field.name]}
                                onChange={(v) => form.setData('meta', { ...form.data.meta, [field.name]: v })}
                                error={form.errors[`meta.${field.name}`]}
                            />
                            {field.description && field.type !== 'checkbox' && <p className="mt-1 text-xs text-slate-500">{field.description}</p>}
                        </div>
                    ))}
                </div>
            )}
            {box.html && <HtmlContent html={box.html} className="pm-html text-sm" interceptForms={false} data-meta-box={box.id} />}
        </Box>
    );
}

function HierarchicalTerms({ tax, form }) {
    const [terms, setTerms] = useState(tax.terms);
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState('');
    const selected = (form.data.terms[tax.name] || []).map(Number);

    const toggle = (id, on) => form.setData('terms', { ...form.data.terms, [tax.name]: on ? [...selected, id] : selected.filter((x) => x !== id) });

    const tree = (parent = null, depth = 0) =>
        terms
            .filter((t) => (t.parent_id ?? null) === parent)
            .flatMap((t) => [
                <label key={t.id} className="flex items-center gap-2 py-0.5 text-sm text-slate-700" style={{ paddingLeft: depth * 16 }}>
                    <input type="checkbox" className="rounded border-slate-300 text-brand-600" checked={selected.includes(t.id)} onChange={(e) => toggle(t.id, e.target.checked)} />
                    {t.name}
                </label>,
                ...tree(t.id, depth + 1),
            ]);

    const add = async () => {
        if (!name.trim()) return;
        const { data } = await window.CMS.axios.post(`/admin/terms/${tax.name}`, { name }, { headers: { Accept: 'application/json' } });
        setTerms((t) => [...t, { id: data.id, name: data.name, parent_id: null }]);
        toggle(data.id, true);
        setName('');
        setAdding(false);
    };

    return (
        <div>
            <div className="max-h-56 overflow-y-auto rounded border border-slate-200 p-2">{terms.length ? tree() : <p className="text-xs text-slate-500">{tax.labels.not_found}</p>}</div>
            {tax.can_manage &&
                (adding ? (
                    <div className="mt-2 flex gap-2">
                        <Input value={name} onChange={(e) => setName(e.target.value)} className="py-1 text-xs" autoFocus onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())} />
                        <Button size="sm" variant="secondary" onClick={add}>
                            Add
                        </Button>
                    </div>
                ) : (
                    <button type="button" className="mt-2 inline-flex items-center gap-1 text-xs text-brand-600 hover:underline" onClick={() => setAdding(true)}>
                        <Plus className="size-3" /> {tax.labels.add_new_item}
                    </button>
                ))}
        </div>
    );
}

function TagInput({ tax, form }) {
    const [input, setInput] = useState('');
    const [suggestions, setSuggestions] = useState([]);
    const tags = form.data.terms[tax.name] || [];

    const add = (value) => {
        const names = value.split(',').map((s) => s.trim()).filter(Boolean);
        const next = [...new Set([...tags, ...names])];
        form.setData('terms', { ...form.data.terms, [tax.name]: next });
        setInput('');
        setSuggestions([]);
    };

    useEffect(() => {
        if (input.trim().length < 2) return setSuggestions([]);
        const t = setTimeout(() => {
            window.CMS.axios.get(`/admin/terms/${tax.name}/json`, { params: { q: input } }).then(({ data }) => setSuggestions(data.filter((d) => !tags.includes(d.name))));
        }, 200);
        return () => clearTimeout(t);
    }, [input]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div>
            <div className="relative flex gap-2">
                <Input
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            add(input);
                        }
                    }}
                    placeholder="Add, separate with commas"
                    className="py-1 text-xs"
                />
                <Button size="sm" variant="secondary" onClick={() => add(input)}>
                    Add
                </Button>
                {suggestions.length > 0 && (
                    <div className="absolute left-0 top-full z-10 mt-1 w-full rounded border border-slate-200 bg-white py-1 shadow">
                        {suggestions.map((s) => (
                            <button key={s.id} type="button" className="block w-full px-3 py-1 text-left text-xs hover:bg-slate-50" onClick={() => add(s.name)}>
                                {s.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <div className="mt-2 flex flex-wrap gap-1.5">
                {tags.map((t) => (
                    <span key={t} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                        {t}
                        <button type="button" onClick={() => form.setData('terms', { ...form.data.terms, [tax.name]: tags.filter((x) => x !== t) })} className="text-slate-400 hover:text-red-600">
                            <X className="size-3" />
                        </button>
                    </span>
                ))}
            </div>
        </div>
    );
}

function CustomFields({ form }) {
    const rows = form.data.custom_fields || [];
    const set = (i, key, value) => form.setData('custom_fields', rows.map((r, idx) => (idx === i ? { ...r, [key]: value } : r)));
    return (
        <Box title="Custom Fields" defaultOpen={rows.length > 0}>
            <div className="space-y-2">
                {rows.map((row, i) => (
                    <div key={i} className="grid grid-cols-[1fr_2fr_auto] gap-2">
                        <Input value={row.key} onChange={(e) => set(i, 'key', e.target.value)} placeholder="Name" className="text-xs" />
                        <Textarea value={row.value ?? ''} onChange={(e) => set(i, 'value', e.target.value)} placeholder="Value" rows={1} className="min-h-0 text-xs" />
                        <Button variant="danger-ghost" size="sm" onClick={() => form.setData('custom_fields', rows.filter((_, idx) => idx !== i))} aria-label="Remove">
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
            </div>
            <Button variant="secondary" size="sm" className="mt-3" icon={Plus} onClick={() => form.setData('custom_fields', [...rows, { key: '', value: '' }])}>
                Add Custom Field
            </Button>
            <p className="mt-2 text-xs text-slate-500">Custom fields can be used to add extra metadata to a post that you can use in your theme.</p>
        </Box>
    );
}

/* ------------------------------------------------------------------ helpers */

/** Inputs inside HTML meta boxes named meta_box[key] (or meta_box[key][]). */
function collectHtmlInputs(root) {
    const out = {};
    root?.querySelectorAll('[data-meta-box] [name]').forEach((el) => {
        const m = el.name.match(/^meta_box\[([^\]]+)\](\[\])?$/);
        if (!m) return;
        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
        if (m[2]) (out[m[1]] ||= []).push(el.value);
        else out[m[1]] = el.value;
    });
    return out;
}

function parentOptions(parents, parent = null, depth = 0) {
    return parents
        .filter((p) => (p.parent_id ?? null) === parent)
        .flatMap((p) => [{ value: p.id, label: `${'— '.repeat(depth)}${p.title || '(no title)'}` }, ...parentOptions(parents, p.id, depth + 1)]);
}

function slugify(s) {
    return (s || '')
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function isFuture(value) {
    return value && new Date(value).getTime() > Date.now() + 60000;
}
