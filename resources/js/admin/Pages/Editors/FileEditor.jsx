import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef } from 'react';
import { FileCode, Folder } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge, Button, Card, cx, Notice, Select } from '../../Components/ui';
import { tabIndent } from '../../Components/FieldRenderer';

/**
 * Theme File Editor & Plugin File Editor.
 */
export default function FileEditor({ kind, title, packages, package: pkg, packageName, isActive, files, file, contents, saveUrl, notice }) {
    const form = useForm({ package: pkg, file, contents });
    const gutter = useRef(null);
    const area = useRef(null);
    const param = kind === 'theme' ? 'theme' : 'plugin';
    const base = kind === 'theme' ? '/admin/theme-editor' : '/admin/plugin-editor';

    useEffect(() => {
        form.setData({ package: pkg, file, contents });
    }, [pkg, file, contents]); // eslint-disable-line react-hooks/exhaustive-deps

    const lines = useMemo(() => (form.data.contents || '').split('\n').length, [form.data.contents]);
    const tree = useMemo(() => buildTree(files), [files]);

    const open = (path) => {
        if (form.isDirty && !window.confirm('You have unsaved changes. Discard them?')) return;
        router.get(base, { [param]: pkg, file: path }, { preserveScroll: true });
    };

    const save = () => form.post(saveUrl, { preserveScroll: true, preserveState: true });

    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                save();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    return (
        <AdminLayout title={title} wide>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    {isActive && <Badge color="green">Active</Badge>}
                </div>
                <label className="flex items-center gap-2 text-sm text-slate-600">
                    Select {kind} to edit:
                    <Select value={pkg} onChange={(e) => router.get(base, { [param]: e.target.value })} options={packages} className="w-64" />
                </label>
            </div>

            <Notice type="warning" className="mb-4">
                {notice}
            </Notice>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                <Card
                    title={
                        <span className="flex items-center gap-2">
                            {packageName}: <code className="font-mono text-xs text-slate-600">{form.data.file}</code>
                            {form.isDirty && <span className="text-xs font-normal text-amber-600">● unsaved</span>}
                        </span>
                    }
                    bodyClassName="p-0"
                    footer={
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-slate-500">{lines} lines · Ctrl/⌘ + S to save</span>
                            <Button onClick={save} loading={form.processing} disabled={!form.data.file}>
                                Update File
                            </Button>
                        </div>
                    }
                >
                    <div className="flex h-[65vh] overflow-hidden bg-slate-950 font-mono text-[13px] leading-6">
                        <div ref={gutter} className="select-none overflow-hidden px-3 py-3 text-right text-slate-600">
                            {Array.from({ length: lines }, (_, i) => (
                                <div key={i}>{i + 1}</div>
                            ))}
                        </div>
                        <textarea
                            ref={area}
                            value={form.data.contents}
                            onChange={(e) => form.setData('contents', e.target.value)}
                            onScroll={(e) => gutter.current && (gutter.current.scrollTop = e.target.scrollTop)}
                            onKeyDown={tabIndent}
                            spellCheck={false}
                            wrap="off"
                            className="flex-1 resize-none border-0 bg-transparent px-3 py-3 text-slate-100 caret-white focus:outline-none focus:ring-0"
                        />
                    </div>
                </Card>

                <Card title="Files" bodyClassName="p-2 max-h-[70vh] overflow-y-auto">
                    <Tree node={tree} current={form.data.file} onOpen={open} />
                </Card>
            </div>
        </AdminLayout>
    );
}

function Tree({ node, current, onOpen, depth = 0 }) {
    return (
        <ul>
            {Object.entries(node.dirs)
                .sort()
                .map(([name, child]) => (
                    <li key={name}>
                        <div className="flex items-center gap-1.5 px-2 py-1 text-xs font-medium text-slate-500" style={{ paddingLeft: depth * 12 + 8 }}>
                            <Folder className="size-3.5" /> {name}
                        </div>
                        <Tree node={child} current={current} onOpen={onOpen} depth={depth + 1} />
                    </li>
                ))}
            {node.files.map((f) => (
                <li key={f.path}>
                    <button
                        type="button"
                        onClick={() => onOpen(f.path)}
                        className={cx('flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-xs', current === f.path ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-700 hover:bg-slate-50')}
                        style={{ paddingLeft: depth * 12 + 8 }}
                    >
                        <FileCode className="size-3.5 shrink-0 text-slate-400" />
                        <span className="truncate">{f.path.split('/').pop()}</span>
                    </button>
                </li>
            ))}
        </ul>
    );
}

function buildTree(files) {
    const root = { dirs: {}, files: [] };
    for (const f of files) {
        const parts = f.path.split('/');
        let node = root;
        for (const dir of parts.slice(0, -1)) node = node.dirs[dir] ||= { dirs: {}, files: [] };
        node.files.push(f);
    }
    return root;
}
