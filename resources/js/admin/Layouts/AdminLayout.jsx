import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ExternalLink, LogOut, Menu as MenuIcon, Plus, User, X } from 'lucide-react';
import Icon from '../Components/Icon';
import { cx, Dropdown, DropdownItem, Notice } from '../Components/ui';
import HtmlContent from '../../shared/HtmlContent';

/**
 * Admin shell: the sidebar is built entirely from the server-side menu
 * (core items + everything plugins add via add_menu_page/add_submenu_page).
 */
export default function AdminLayout({ title, children, wide = false }) {
    const { adminMenu = [], adminNotices = [], auth, site, flash, cms } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [toast, setToast] = useState(null);
    const [dismissed, setDismissed] = useState([]);

    const currentUrl = usePage().url;
    const activeMenu = useMemo(() => findActive(adminMenu, currentUrl), [adminMenu, currentUrl]);

    useEffect(() => {
        const msg = flash?.success || flash?.error || flash?.info;
        if (msg) {
            setToast({ type: flash.error ? 'error' : flash.success ? 'success' : 'info', message: msg, id: Date.now() });
        }
    }, [flash]);

    useEffect(() => {
        if (!toast || toast.type === 'error') return;
        const t = setTimeout(() => setToast(null), 5000);
        return () => clearTimeout(t);
    }, [toast]);

    useEffect(() => router.on('navigate', () => setMobileOpen(false)), []);

    return (
        <div className="min-h-screen bg-slate-100">
            <Head title={title ? `${title} ‹ ${site?.name ?? ''}` : site?.name} />

            {/* Top bar */}
            <header className="fixed inset-x-0 top-0 z-30 flex h-12 items-center gap-2 bg-slate-950 px-3 text-sm text-slate-200">
                <button className="rounded p-1.5 hover:bg-slate-800 lg:hidden" onClick={() => setMobileOpen((o) => !o)} aria-label="Toggle menu">
                    {mobileOpen ? <X className="size-5" /> : <MenuIcon className="size-5" />}
                </button>
                <a href={site?.url} className="flex items-center gap-2 rounded px-2 py-1 font-medium hover:bg-slate-800 hover:text-white" target="_blank" rel="noreferrer">
                    <span className="grid size-6 place-items-center rounded bg-brand-600 text-xs font-bold text-white">{(cms?.name || 'P')[0]}</span>
                    <span className="max-w-48 truncate">{site?.name}</span>
                    <ExternalLink className="size-3.5 opacity-60" />
                </a>
                {auth?.user?.can?.edit_posts && (
                    <Dropdown
                        align="left"
                        trigger={
                            <button className="flex items-center gap-1 rounded px-2 py-1 hover:bg-slate-800 hover:text-white">
                                <Plus className="size-4" /> New
                            </button>
                        }
                    >
                        <DropdownItem href="/admin/content/post/create">Post</DropdownItem>
                        <DropdownItem href="/admin/content/page/create">Page</DropdownItem>
                        <DropdownItem href="/admin/media">Media</DropdownItem>
                        <DropdownItem href="/admin/users/create">User</DropdownItem>
                    </Dropdown>
                )}
                <div className="ml-auto" />
                {auth?.user && (
                    <Dropdown
                        trigger={
                            <button className="flex items-center gap-2 rounded px-2 py-1 hover:bg-slate-800">
                                <span className="hidden sm:inline">Howdy, {auth.user.name}</span>
                                <img src={auth.user.avatar} alt="" className="size-6 rounded-full bg-slate-700" />
                                <ChevronDown className="size-3.5" />
                            </button>
                        }
                    >
                        <div className="border-b border-slate-100 px-3 py-2 text-xs text-slate-500">
                            Signed in as <strong className="text-slate-700">{auth.user.username}</strong>
                        </div>
                        <DropdownItem href="/admin/profile">
                            <span className="flex items-center gap-2">
                                <User className="size-4" /> Edit Profile
                            </span>
                        </DropdownItem>
                        <DropdownItem href="/logout" method="post" as="button">
                            <span className="flex items-center gap-2">
                                <LogOut className="size-4" /> Log Out
                            </span>
                        </DropdownItem>
                    </Dropdown>
                )}
            </header>

            {/* Sidebar */}
            <aside
                className={cx(
                    'fixed bottom-0 left-0 top-12 z-20 w-60 overflow-y-auto bg-slate-900 pb-10 text-slate-300 transition-transform lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <nav className="py-2">
                    {adminMenu.map((item) => (
                        <MenuItem key={item.slug} item={item} active={activeMenu} />
                    ))}
                </nav>
                <p className="px-4 pt-6 text-xs text-slate-500">
                    {cms?.name} {cms?.version}
                </p>
            </aside>
            {mobileOpen && <div className="fixed inset-0 z-10 bg-black/30 lg:hidden" onClick={() => setMobileOpen(false)} />}

            {/* Content */}
            <main className="pt-12 lg:pl-60">
                <div className={cx('mx-auto p-4 sm:p-6 lg:p-8', wide ? 'max-w-none' : 'max-w-screen-2xl')}>
                    <div className="mb-4 space-y-2 empty:hidden">
                        {adminNotices
                            .filter((n, i) => !dismissed.includes(i))
                            .map((n, i) => (
                                <Notice
                                    key={i}
                                    type={n.type}
                                    onDismiss={() => {
                                        setDismissed((d) => [...d, i]);
                                        if (n.dismiss) router.post(n.dismiss, {}, { preserveScroll: true, preserveState: true });
                                    }}
                                >
                                    {n.html ? <HtmlContent html={n.message} /> : n.message}
                                </Notice>
                            ))}
                    </div>
                    {children}
                </div>
            </main>

            {/* Flash toast */}
            {toast && (
                <div className="fixed bottom-5 right-5 z-50 w-[22rem] max-w-[calc(100vw-2.5rem)]">
                    <Notice type={toast.type} onDismiss={() => setToast(null)} className="shadow-lg">
                        {toast.message}
                    </Notice>
                </div>
            )}
        </div>
    );
}

function MenuItem({ item, active }) {
    const isActive = active?.parent === item.slug;
    const [open, setOpen] = useState(isActive);
    useEffect(() => setOpen(isActive), [isActive]);
    const hasChildren = item.children?.length > 0;

    return (
        <div className={cx(item.separator_before && 'mt-2 border-t border-slate-800 pt-2')}>
            <div className="flex items-stretch">
                <Link
                    href={item.url}
                    className={cx(
                        'flex flex-1 items-center gap-3 px-4 py-2 text-sm transition-colors',
                        isActive ? 'bg-brand-600 text-white' : 'hover:bg-slate-800 hover:text-white',
                    )}
                >
                    <Icon name={item.icon} className="size-[18px] shrink-0 opacity-90" />
                    <span className="flex-1 truncate">{item.title}</span>
                    {item.badge ? <span className="rounded-full bg-amber-500 px-1.5 text-[11px] font-semibold text-white">{item.badge}</span> : null}
                </Link>
                {hasChildren && !isActive && (
                    <button
                        className="px-2 text-slate-500 hover:bg-slate-800 hover:text-white"
                        onClick={() => setOpen((o) => !o)}
                        aria-label={`Toggle ${item.title} submenu`}
                    >
                        <ChevronDown className={cx('size-4 transition-transform', open && 'rotate-180')} />
                    </button>
                )}
            </div>
            {hasChildren && (open || isActive) && (
                <div className={cx('py-1', isActive ? 'bg-slate-950/60' : 'bg-slate-950/30')}>
                    {item.children.map((child) => (
                        <Link
                            key={child.slug}
                            href={child.url}
                            className={cx(
                                'flex items-center gap-2 py-1.5 pl-11 pr-4 text-[13px] transition-colors',
                                active?.child === child.slug ? 'font-semibold text-white' : 'text-slate-400 hover:text-white',
                            )}
                        >
                            <span className="flex-1 truncate">{child.title}</span>
                            {child.badge ? <span className="rounded-full bg-amber-500 px-1.5 text-[11px] font-semibold text-white">{child.badge}</span> : null}
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Find the menu entry matching the current URL (longest prefix wins).
 */
function findActive(menu, currentUrl) {
    const current = new URL(currentUrl, window.location.origin);
    const path = current.pathname.replace(/\/+$/, '') || '/';
    let best = null;

    const score = (url) => {
        if (!url || url === '#') return -1;
        const u = new URL(url, window.location.origin);
        const p = u.pathname.replace(/\/+$/, '') || '/';
        let s = -1;
        if (p === path) s = 1000 + p.length;
        else if (path.startsWith(p + '/') && p !== '/admin') s = p.length;
        // Query-sensitive items (e.g. taxonomy screens per post type)
        if (s >= 0 && u.search) {
            const wanted = new URLSearchParams(u.search);
            for (const [k, v] of wanted) {
                if (current.searchParams.get(k) === v) s += 10;
            }
        }
        return s;
    };

    for (const item of menu) {
        const own = score(item.url);
        if (own >= 0 && (!best || own > best.score)) best = { parent: item.slug, child: null, score: own };
        for (const child of item.children || []) {
            const s = score(child.url);
            if (s >= 0 && (!best || s >= best.score)) best = { parent: item.slug, child: child.slug, score: s };
        }
    }
    return best;
}
