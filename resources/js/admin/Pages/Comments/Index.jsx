import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { MessageSquare } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, EmptyState, Input, Modal, Pagination, SearchBox, Select, StatusTabs, Textarea } from '../../Components/ui';

export default function CommentsIndex({ comments, counts, filters }) {
    const [selected, setSelected] = useState([]);
    const [bulk, setBulk] = useState('');
    const [editing, setEditing] = useState(null);
    const [replying, setReplying] = useState(null);
    const status = filters.status || 'all';

    const tabs = [
        { key: 'all', label: 'All', count: counts.all },
        { key: 'pending', label: 'Pending', count: counts.pending },
        { key: 'approved', label: 'Approved', count: counts.approved },
        { key: 'spam', label: 'Spam', count: counts.spam },
        { key: 'trash', label: 'Trash', count: counts.trash },
    ];

    const setStatus = (id, s) => router.patch(`/admin/comments/${id}/status`, { status: s }, { preserveScroll: true });
    const bulkOptions =
        status === 'trash' || status === 'spam'
            ? { approved: 'Restore / Approve', delete: 'Delete Permanently' }
            : { approved: 'Approve', pending: 'Unapprove', spam: 'Mark as Spam', trash: 'Move to Trash' };

    return (
        <AdminLayout title="Comments">
            <h1 className="mb-4 text-2xl font-semibold tracking-tight">Comments</h1>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <StatusTabs tabs={tabs} active={status} buildHref={(key) => `/admin/comments?status=${key}`} />
                <SearchBox value={filters.s} onSearch={(s) => router.get('/admin/comments', { ...filters, s }, { preserveState: true })} placeholder="Search comments" />
            </div>
            <div className="mb-3 flex items-center gap-2">
                <Select value={bulk} onChange={(e) => setBulk(e.target.value)} className="w-48" placeholder="Bulk actions" options={bulkOptions} />
                <Button
                    variant="secondary"
                    disabled={!bulk || !selected.length}
                    onClick={() => router.post('/admin/comments/bulk', { action: bulk, ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) })}
                >
                    Apply
                </Button>
                {filters.post_id && (
                    <Link href="/admin/comments" className="text-sm text-brand-600 hover:underline">
                        Show comments for all posts
                    </Link>
                )}
            </div>

            <Card bodyClassName="p-0">
                <table className="w-full text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="w-10 px-4 py-2.5">
                                <input type="checkbox" className="rounded border-slate-300" checked={comments.data.length > 0 && selected.length === comments.data.length} onChange={(e) => setSelected(e.target.checked ? comments.data.map((c) => c.id) : [])} />
                            </th>
                            <th className="w-56 px-3 py-2.5 font-medium">Author</th>
                            <th className="px-3 py-2.5 font-medium">Comment</th>
                            <th className="w-56 px-3 py-2.5 font-medium">In response to</th>
                            <th className="w-40 px-3 py-2.5 font-medium">Submitted on</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {comments.data.map((c) => (
                            <tr key={c.id} className={`group align-top ${c.status === 'pending' ? 'bg-amber-50/60' : 'hover:bg-slate-50/70'}`}>
                                <td className="px-4 py-3">
                                    <input type="checkbox" className="rounded border-slate-300" checked={selected.includes(c.id)} onChange={(e) => setSelected((s) => (e.target.checked ? [...s, c.id] : s.filter((x) => x !== c.id)))} />
                                </td>
                                <td className="px-3 py-3">
                                    <div className="flex items-start gap-2">
                                        <img src={c.avatar} alt="" className="size-8 rounded-full bg-slate-200" />
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-slate-800">{c.author_name}</p>
                                            {c.author_email && <p className="truncate text-xs text-brand-600">{c.author_email}</p>}
                                            {c.author_ip && <p className="text-xs text-slate-400">{c.author_ip}</p>}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-3 py-3">
                                    {c.in_reply_to && <p className="mb-1 text-xs text-slate-500">In reply to {c.in_reply_to}</p>}
                                    <p className="whitespace-pre-line text-slate-700">{c.content}</p>
                                    <div className="mt-2 flex flex-wrap gap-x-2 text-xs md:opacity-0 md:group-hover:opacity-100">
                                        {c.status === 'pending' && <Action onClick={() => setStatus(c.id, 'approved')} className="text-emerald-700">Approve</Action>}
                                        {c.status === 'approved' && <Action onClick={() => setStatus(c.id, 'pending')} className="text-amber-700">Unapprove</Action>}
                                        {['approved', 'pending'].includes(c.status) && (
                                            <>
                                                <Action onClick={() => setReplying(c)}>Reply</Action>
                                                <Action onClick={() => setEditing(c)}>Edit</Action>
                                                <Action onClick={() => setStatus(c.id, 'spam')} className="text-red-600">Spam</Action>
                                                <Action onClick={() => setStatus(c.id, 'trash')} className="text-red-600">Trash</Action>
                                            </>
                                        )}
                                        {['spam', 'trash'].includes(c.status) && (
                                            <>
                                                <Action onClick={() => setStatus(c.id, 'approved')}>{c.status === 'spam' ? 'Not Spam' : 'Restore'}</Action>
                                                <Action onClick={() => window.confirm('Delete permanently?') && router.delete(`/admin/comments/${c.id}`, { preserveScroll: true })} className="text-red-600">
                                                    Delete Permanently
                                                </Action>
                                            </>
                                        )}
                                    </div>
                                </td>
                                <td className="px-3 py-3">
                                    {c.post ? (
                                        <>
                                            <Link href={c.post.edit_url} className="font-medium text-brand-700 hover:underline">
                                                {c.post.title}
                                            </Link>
                                            <a href={c.post.view_url} target="_blank" rel="noreferrer" className="block text-xs text-slate-500 hover:underline">
                                                View Post
                                            </a>
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </td>
                                <td className="px-3 py-3 text-slate-600">
                                    {c.date}
                                    {c.status === 'pending' && <Badge color="amber" className="mt-1">Pending</Badge>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!comments.data.length && <EmptyState icon={MessageSquare} title="No comments found." />}
            </Card>
            <div className="mt-4">
                <Pagination meta={comments} />
            </div>

            {editing && <EditComment comment={editing} onClose={() => setEditing(null)} />}
            {replying && <ReplyComment comment={replying} onClose={() => setReplying(null)} />}
        </AdminLayout>
    );
}

function Action({ onClick, children, className = 'text-brand-600' }) {
    return (
        <button type="button" onClick={onClick} className={`hover:underline ${className}`}>
            {children}
        </button>
    );
}

function EditComment({ comment, onClose }) {
    const form = useForm({ content: comment.content, author_name: comment.author_name || '', author_email: comment.author_email || '', author_url: comment.author_url || '' });
    return (
        <Modal
            open
            onClose={onClose}
            title="Edit Comment"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button onClick={() => form.put(`/admin/comments/${comment.id}`, { preserveScroll: true, onSuccess: onClose })} loading={form.processing}>
                        Update Comment
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Input value={form.data.author_name} onChange={(e) => form.setData('author_name', e.target.value)} placeholder="Name" />
                    <Input value={form.data.author_email} onChange={(e) => form.setData('author_email', e.target.value)} placeholder="Email" />
                    <Input value={form.data.author_url} onChange={(e) => form.setData('author_url', e.target.value)} placeholder="URL" />
                </div>
                <Textarea value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} rows={8} />
                {form.errors.content && <p className="text-xs text-red-600">{form.errors.content}</p>}
            </div>
        </Modal>
    );
}

function ReplyComment({ comment, onClose }) {
    const form = useForm({ content: '' });
    return (
        <Modal
            open
            onClose={onClose}
            title={`Reply to ${comment.author_name}`}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button onClick={() => form.post(`/admin/comments/${comment.id}/reply`, { preserveScroll: true, onSuccess: onClose })} loading={form.processing}>
                        {comment.status === 'pending' ? 'Approve and Reply' : 'Reply'}
                    </Button>
                </>
            }
        >
            <blockquote className="mb-3 border-l-4 border-slate-200 pl-3 text-sm italic text-slate-600">{comment.content}</blockquote>
            <Textarea value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} rows={6} autoFocus />
        </Modal>
    );
}
