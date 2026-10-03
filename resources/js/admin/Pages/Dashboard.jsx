import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Brush, FilePlus, LayoutTemplate, MessageSquare, Plug, Settings } from 'lucide-react';
import AdminLayout from '../Layouts/AdminLayout';
import Icon from '../Components/Icon';
import { Badge, Button, Card, Input, Notice, Textarea } from '../Components/ui';
import HtmlContent from '../../shared/HtmlContent';
import { useRegistry } from '../../shared/registry';

export default function Dashboard({ widgets }) {
    const custom = useRegistry(window.CMS.registries.adminComponents);
    const normal = widgets.filter((w) => w.context !== 'side');
    const side = widgets.filter((w) => w.context === 'side');

    const render = (w) => {
        const Core = CORE[w.component];
        if (Core) return <Core key={w.id} title={w.title} {...w.props} />;

        const Plugin = w.component ? custom[w.component] : null;
        return (
            <Card key={w.id} title={w.title}>
                {Plugin ? (
                    <Plugin {...w.props} />
                ) : w.component ? (
                    <p className="text-sm text-slate-500">Loading {w.component}…</p>
                ) : (
                    <HtmlContent html={w.html} className="pm-html text-sm" />
                )}
            </Card>
        );
    };

    return (
        <AdminLayout title="Dashboard">
            <h1 className="mb-6 text-2xl font-semibold tracking-tight">Dashboard</h1>
            {normal.filter((w) => w.component === 'core/welcome').map(render)}
            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">{normal.filter((w) => w.component !== 'core/welcome').map(render)}</div>
                <div className="space-y-6">{side.map(render)}</div>
            </div>
        </AdminLayout>
    );
}

function Welcome() {
    const { site } = usePage().props;
    const links = [
        { icon: Brush, label: 'Customize your site', href: '/admin/customize' },
        { icon: LayoutTemplate, label: 'Choose a theme', href: '/admin/themes' },
        { icon: Plug, label: 'Add plugins', href: '/admin/plugins' },
        { icon: FilePlus, label: 'Write your first post', href: '/admin/content/post/create' },
        { icon: Settings, label: 'Configure settings', href: '/admin/settings/general' },
        { icon: MessageSquare, label: 'Manage comments', href: '/admin/comments' },
    ];
    return (
        <section className="overflow-hidden rounded-xl bg-gradient-to-br from-brand-600 via-brand-700 to-slate-900 p-6 text-white shadow-sm sm:p-8">
            <h2 className="text-2xl font-semibold">Welcome to {site?.name}!</h2>
            <p className="mt-1 text-brand-100">Here are some links to help you get started.</p>
            <div className="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {links.map(({ icon: I, label, href }) => (
                    <Link key={href} href={href} className="group flex items-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm font-medium backdrop-blur transition hover:bg-white/20">
                        <I className="size-5 text-brand-200" />
                        <span className="flex-1">{label}</span>
                        <ArrowRight className="size-4 opacity-0 transition group-hover:opacity-100" />
                    </Link>
                ))}
            </div>
        </section>
    );
}

function AtAGlance({ title, items = [], pending_comments, theme, version, search_discouraged }) {
    return (
        <Card title={title}>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {items.map((item) => (
                    <Link key={item.label} href={item.url} className="flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:border-brand-300 hover:bg-brand-50/40">
                        <Icon name={item.icon} className="size-5 text-slate-400" />
                        <span>
                            <span className="block text-lg font-semibold leading-none text-slate-900">{item.count}</span>
                            <span className="text-xs text-slate-500">{item.label}</span>
                        </span>
                    </Link>
                ))}
            </div>
            {pending_comments > 0 && (
                <p className="mt-4 text-sm">
                    <Link href="/admin/comments?status=pending" className="text-amber-700 hover:underline">
                        {pending_comments} comment{pending_comments === 1 ? '' : 's'} awaiting moderation
                    </Link>
                </p>
            )}
            <p className="mt-4 text-sm text-slate-600">
                Running version {version} with the <Link href="/admin/themes" className="text-brand-600 hover:underline">{theme}</Link> theme.
            </p>
            {search_discouraged && (
                <Notice type="warning" className="mt-3">
                    Search engines discouraged
                </Notice>
            )}
        </Card>
    );
}

function Activity({ title, scheduled = [], recent = [], comments = [] }) {
    return (
        <Card title={title}>
            {scheduled.length > 0 && <PostList heading="Publishing Soon" items={scheduled} />}
            <PostList heading="Recently Published" items={recent} empty="Nothing published yet." />
            <h3 className="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Recent Comments</h3>
            {comments.length === 0 && <p className="text-sm text-slate-500">No comments yet.</p>}
            <ul className="divide-y divide-slate-100">
                {comments.map((c) => (
                    <li key={c.id} className="py-2 text-sm">
                        <p className="text-slate-600">
                            From <strong className="text-slate-800">{c.author}</strong> on{' '}
                            {c.post_url ? (
                                <Link href={c.post_url} className="text-brand-600 hover:underline">
                                    {c.post}
                                </Link>
                            ) : (
                                c.post
                            )}{' '}
                            {c.status === 'pending' && <Badge color="amber">Pending</Badge>}
                        </p>
                        <p className="text-slate-500">{c.excerpt}</p>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function PostList({ heading, items, empty }) {
    return (
        <>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{heading}</h3>
            {!items.length && empty && <p className="mb-2 text-sm text-slate-500">{empty}</p>}
            <ul className="mb-4 space-y-1.5">
                {items.map((p) => (
                    <li key={p.id} className="flex gap-3 text-sm">
                        <span className="w-28 shrink-0 text-slate-500">{p.date}</span>
                        <Link href={p.edit_url} className="truncate text-brand-600 hover:underline">
                            {p.title}
                        </Link>
                    </li>
                ))}
            </ul>
        </>
    );
}

function QuickDraft({ title, drafts = [] }) {
    const form = useForm({ title: '', content: '' });
    return (
        <Card title={title}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/quick-draft', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="space-y-3"
            >
                <Input placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <Textarea placeholder="What’s on your mind?" value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} rows={4} />
                <Button type="submit" variant="secondary" loading={form.processing}>
                    Save Draft
                </Button>
            </form>
            {drafts.length > 0 && (
                <>
                    <h3 className="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Your Recent Drafts</h3>
                    <ul className="space-y-1.5">
                        {drafts.map((d) => (
                            <li key={d.id} className="text-sm">
                                <Link href={d.edit_url} className="text-brand-600 hover:underline">
                                    {d.title}
                                </Link>{' '}
                                <span className="text-slate-500">{d.date}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </Card>
    );
}

const CORE = {
    'core/welcome': Welcome,
    'core/at-a-glance': AtAGlance,
    'core/activity': Activity,
    'core/quick-draft': QuickDraft,
};
