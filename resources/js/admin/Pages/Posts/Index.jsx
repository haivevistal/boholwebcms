import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FileText, MessageSquare } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, EmptyState, Pagination, SearchBox, Select, StatusTabs } from '../../Components/ui';
import HtmlContent from '../../../shared/HtmlContent';

const STATUS_BADGE = { draft: ['Draft', 'slate'], pending: ['Pending', 'amber'], private: ['Private', 'blue'], future: ['Scheduled', 'brand'], trash: ['Trash', 'red'] };

export default function PostsIndex({ postType, posts, columns, statusTabs, filters, months, filterTerms, filterTaxonomy, bulkActions, can }) {
    const base = `/admin/content/${postType.name}`;
    const [selected, setSelected] = useState([]);
    const [bulk, setBulk] = useState('');
    const [month, setMonth] = useState(filters.m || '');
    const [term, setTerm] = useState(filters.term || '');

    const visit = (params) => router.get(base, { ...filters, ...params }, { preserveState: true, preserveScroll: true });

    // Indent hierarchical items (pages) under their parent when listed together.
    const rows = useMemo(() => {
        if (!postType.hierarchical || filters.s || filters.orderby) return posts.data.map((p) => ({ ...p, depth: 0 }));
        const byParent = {};
        posts.data.forEach((p) => (byParent[p.parent_id ?? 'root'] ||= []).push(p));
        const ids = new Set(posts.data.map((p) => p.id));
        const out = [];
        const walk = (parent, depth) => (byParent[parent] || []).forEach((p) => (out.push({ ...p, depth }), walk(p.id, depth + 1)));
        walk('root', 0);
        posts.data.filter((p) => p.parent_id && !ids.has(p.parent_id)).forEach((p) => !out.find((o) => o.id === p.id) && (out.push({ ...p, depth: 0 }), walk(p.id, 1)));
        return out;
    }, [posts.data, postType.hierarchical, filters.s, filters.orderby]);

    const allSelected = rows.length > 0 && selected.length === rows.length;
    const isTrash = filters.status === 'trash';

    const applyBulk = () => {
        if (!bulk || !selected.length) return;
        if (bulk === 'delete' && !window.confirm('Permanently delete the selected items?')) return;
        router.post(`${base}/bulk`, { action: bulk, ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    };

    const sortLink = (key, label) => {
        const active = filters.orderby === key;
        const order = active && filters.order === 'asc' ? 'desc' : 'asc';
        return (
            <button type="button" onClick={() => visit({ orderby: key, order })} className="inline-flex items-center gap-1 hover:text-slate-900">
                {label} {active && <span className="text-xs">{filters.order === 'asc' ? '▲' : '▼'}</span>}
            </button>
        );
    };

    return (
        <AdminLayout title={postType.label}>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">{postType.label}</h1>
                {can.create && (
                    <Button href={`${base}/create`} variant="secondary" size="sm">
                        {postType.labels.add_new}
                    </Button>
                )}
                {filters.s && <span className="text-sm text-slate-500">Search results for “{filters.s}”</span>}
            </div>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <StatusTabs tabs={statusTabs} active={filters.status || 'all'} buildHref={(key) => `${base}?status=${key}`} />
                <SearchBox value={filters.s} onSearch={(s) => visit({ s, page: undefined })} placeholder={postType.labels.search_items} />
            </div>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Select value={bulk} onChange={(e) => setBulk(e.target.value)} className="w-44" placeholder="Bulk actions" options={bulkActions} />
                <Button variant="secondary" onClick={applyBulk} disabled={!bulk || !selected.length}>
                    Apply
                </Button>
                <span className="mx-1" />
                {months.length > 0 && (
                    <Select value={month} onChange={(e) => setMonth(e.target.value)} className="w-40" placeholder="All dates" options={months} />
                )}
                {filterTaxonomy && filterTerms.length > 0 && (
                    <Select value={term} onChange={(e) => setTerm(e.target.value)} className="w-44" placeholder={`All ${filterTaxonomy.label}`} options={filterTerms.map((t) => ({ value: t.id, label: t.name }))} />
                )}
                <Button variant="secondary" onClick={() => visit({ m: month || undefined, term: term || undefined, page: undefined })}>
                    Filter
                </Button>
                {isTrash && can.delete_others && rows.length > 0 && (
                    <Button variant="danger-ghost" onClick={() => window.confirm('Permanently delete everything in the trash?') && router.post(`${base}/empty-trash`, {}, { preserveScroll: true })}>
                        Empty Trash
                    </Button>
                )}
            </div>

            <Card bodyClassName="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                            <tr>
                                {Object.entries(columns).map(([key, label]) =>
                                    key === 'cb' ? (
                                        <th key={key} className="w-10 px-4 py-2.5">
                                            <input type="checkbox" className="rounded border-slate-300" checked={allSelected} onChange={(e) => setSelected(e.target.checked ? rows.map((r) => r.id) : [])} />
                                        </th>
                                    ) : (
                                        <th key={key} className={`px-3 py-2.5 font-medium ${key === 'title' ? '' : 'whitespace-nowrap'} ${key === 'thumbnail' ? 'w-16' : ''}`}>
                                            {key === 'title' ? sortLink('title', label) : key === 'date' ? sortLink('published_at', label) : key === 'comments' ? <MessageSquare className="size-4" title="Comments" /> : label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((post) => (
                                <tr key={post.id} className="group hover:bg-slate-50/70">
                                    {Object.keys(columns).map((key) => (
                                        <td key={key} className={key === 'cb' ? 'px-4 py-3 align-top' : 'px-3 py-3 align-top'}>
                                            <Cell column={key} post={post} base={base} isTrash={isTrash} selected={selected} setSelected={setSelected} />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!rows.length && (
                    <EmptyState icon={FileText} title={postType.labels.not_found}>
                        {can.create && !isTrash && (
                            <Link href={`${base}/create`} className="text-brand-600 hover:underline">
                                {postType.labels.add_new_item}
                            </Link>
                        )}
                    </EmptyState>
                )}
            </Card>
            <div className="mt-4">
                <Pagination meta={posts} />
            </div>
        </AdminLayout>
    );
}

function Cell({ column, post, base, isTrash, selected, setSelected }) {
    switch (column) {
        case 'cb':
            return (
                <input
                    type="checkbox"
                    className="rounded border-slate-300"
                    checked={selected.includes(post.id)}
                    onChange={(e) => setSelected((s) => (e.target.checked ? [...s, post.id] : s.filter((id) => id !== post.id)))}
                />
            );
        case 'thumbnail':
            return post.thumbnail ? <img src={post.thumbnail} alt="" className="size-10 rounded object-cover" /> : <span className="block size-10 rounded bg-slate-100" />;
        case 'title': {
            const [label, color] = STATUS_BADGE[post.status] || [];
            return (
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        {post.depth > 0 && <span className="text-slate-400">{'— '.repeat(post.depth)}</span>}
                        {post.can_edit && !isTrash ? (
                            <Link href={`${base}/${post.id}/edit`} className="font-semibold text-brand-700 hover:text-brand-800">
                                {post.title || '(no title)'}
                            </Link>
                        ) : (
                            <span className="font-semibold text-slate-700">{post.title || '(no title)'}</span>
                        )}
                        {label && !isTrash && <Badge color={color}>{label}</Badge>}
                        {post.is_front_page && <Badge color="green">Front Page</Badge>}
                        {post.is_posts_page && <Badge color="green">Posts Page</Badge>}
                        {post.is_privacy_page && <Badge color="blue">Privacy Policy Page</Badge>}
                    </div>
                    <RowActions post={post} base={base} isTrash={isTrash} />
                </div>
            );
        }
        case 'author':
            return <span className="text-slate-600">{post.author || '—'}</span>;
        case 'comments':
            return (
                <Link href={`/admin/comments?post_id=${post.id}`} className="inline-flex min-w-7 justify-center rounded bg-slate-200 px-1.5 text-xs font-semibold text-slate-700 hover:bg-brand-600 hover:text-white">
                    {post.comment_count}
                </Link>
            );
        case 'date':
            return (
                <span className="whitespace-nowrap text-slate-600">
                    <span className="block text-xs">{post.status === 'publish' ? 'Published' : post.status === 'future' ? 'Scheduled' : 'Last Modified'}</span>
                    {post.status === 'publish' || post.status === 'future' ? post.date : post.modified}
                </span>
            );
        default:
            if (column.startsWith('taxonomy-')) {
                const tax = column.slice(9);
                const terms = post.terms?.[tax] || [];
                return terms.length ? (
                    <span className="text-slate-600">
                        {terms.map((t, i) => (
                            <span key={t.id}>
                                {i > 0 && ', '}
                                <Link href={`${base}?term=${t.id}`} className="text-brand-600 hover:underline">
                                    {t.name}
                                </Link>
                            </span>
                        ))}
                    </span>
                ) : (
                    <span className="text-slate-400">—</span>
                );
            }
            return <HtmlContent html={post.custom?.[column] ?? ''} className="pm-html text-sm" />;
    }
}

function RowActions({ post, base, isTrash }) {
    const post_ = (url, opts = {}) => router.post(url, {}, { preserveScroll: true, ...opts });
    const actions = [];

    if (isTrash) {
        if (post.can_delete) {
            actions.push(<button key="restore" onClick={() => post_(`${base}/${post.id}/restore`)} className="text-brand-600 hover:underline">Restore</button>);
            actions.push(
                <button key="delete" onClick={() => window.confirm('Delete permanently?') && router.delete(`${base}/${post.id}`, { preserveScroll: true })} className="text-red-600 hover:underline">
                    Delete Permanently
                </button>,
            );
        }
    } else {
        if (post.can_edit) actions.push(<Link key="edit" href={`${base}/${post.id}/edit`} className="text-brand-600 hover:underline">Edit</Link>);
        if (post.can_edit) actions.push(<button key="dup" onClick={() => post_(`${base}/${post.id}/duplicate`)} className="text-brand-600 hover:underline">Duplicate</button>);
        if (post.can_delete) actions.push(<button key="trash" onClick={() => post_(`${base}/${post.id}/trash`)} className="text-red-600 hover:underline">Trash</button>);
        actions.push(
            <a key="view" href={post.status === 'publish' ? post.permalink : post.preview_url} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline">
                {post.status === 'publish' ? 'View' : 'Preview'}
            </a>,
        );
        Object.entries(post.actions || {}).forEach(([key, html]) => actions.push(<HtmlContent key={key} as="span" html={html} className="pm-html" />));
    }

    return (
        <div className="mt-1 flex flex-wrap gap-x-2 text-xs opacity-100 transition-opacity md:opacity-0 md:group-hover:opacity-100">
            {actions.map((a, i) => (
                <span key={i} className="flex items-center gap-2">
                    {i > 0 && <span className="text-slate-300">|</span>}
                    {a}
                </span>
            ))}
        </div>
    );
}
