import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Monitor, RefreshCw, Smartphone, Tablet, X } from 'lucide-react';
import FieldRenderer from '../../Components/FieldRenderer';
import { Button, cx } from '../../Components/ui';

/**
 * Live Customizer: controls on the left, the real site in an iframe on the right.
 * Theme-mod changes are posted to the preview with postMessage and applied
 * instantly; option changes that need the server (homepage settings) reload it.
 */
export default function Customize({ theme, sections, values: initial, media: initialMedia, previewUrl }) {
    const [values, setValues] = useState(initial);
    const [media, setMedia] = useState(initialMedia || {});
    const [section, setSection] = useState(null);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [device, setDevice] = useState('desktop');
    const [needsRefresh, setNeedsRefresh] = useState(false);
    const frame = useRef(null);

    const controlsById = Object.fromEntries(sections.flatMap((s) => s.controls).map((c) => [c.id, c]));

    const post = (vals) => {
        const mods = {};
        const options = {};
        for (const [id, v] of Object.entries(vals)) {
            if (controlsById[id]?.setting_type === 'option') options[id] = v;
            else mods[id] = v;
        }
        frame.current?.contentWindow?.postMessage({ type: 'cms:customize', mods, options, media }, window.location.origin);
    };

    useEffect(() => {
        const onMessage = (e) => e.data?.type === 'cms:customize-ready' && post(values);
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    });

    // Re-send when a newly picked image URL becomes known.
    useEffect(() => post(values), [media]); // eslint-disable-line react-hooks/exhaustive-deps

    const change = (id, value) => {
        const next = { ...values, [id]: value };
        setValues(next);
        setDirty(true);
        if (controlsById[id]?.refresh) setNeedsRefresh(true);
        else post(next);
    };

    const publish = () => {
        setSaving(true);
        router.post(
            '/admin/customize',
            { values },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setDirty(false);
                    if (needsRefresh) {
                        frame.current?.contentWindow?.location.reload();
                        setNeedsRefresh(false);
                    }
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const current = sections.find((s) => s.id === section);
    const widths = { desktop: '100%', tablet: '768px', mobile: '375px' };

    return (
        <div className="flex h-screen overflow-hidden bg-slate-100">
            <Head title={`Customize: ${theme.name}`} />
            <aside className="flex w-80 shrink-0 flex-col border-r border-slate-200 bg-white">
                <header className="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                    <Link href="/admin/themes" className="rounded p-1.5 text-slate-500 hover:bg-slate-100" title="Close" onClick={(e) => dirty && !window.confirm('Leave without publishing your changes?') && e.preventDefault()}>
                        <X className="size-5" />
                    </Link>
                    <Button size="sm" onClick={publish} loading={saving} disabled={!dirty}>
                        {dirty ? 'Publish' : 'Published'}
                    </Button>
                </header>

                <div className="flex-1 overflow-y-auto">
                    {!current ? (
                        <>
                            <div className="border-b border-slate-200 px-4 py-4">
                                <p className="text-xs text-slate-500">You are customizing</p>
                                <h1 className="text-lg font-semibold">{theme.name}</h1>
                            </div>
                            <nav>
                                {sections.map((s) => (
                                    <button key={s.id} type="button" onClick={() => setSection(s.id)} className="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-left text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        {s.title}
                                        <ChevronRight className="size-4 text-slate-400" />
                                    </button>
                                ))}
                            </nav>
                        </>
                    ) : (
                        <>
                            <div className="flex items-center gap-2 border-b border-slate-200 px-2 py-3">
                                <button type="button" onClick={() => setSection(null)} className="rounded p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Back">
                                    <ChevronLeft className="size-5" />
                                </button>
                                <div>
                                    <p className="text-xs text-slate-500">Customizing</p>
                                    <h2 className="font-semibold">{current.title}</h2>
                                </div>
                            </div>
                            <div className="space-y-5 p-4">
                                {current.description && <p className="text-sm text-slate-600">{current.description}</p>}
                                {current.controls.map((control) => (
                                    <div key={control.id}>
                                        {control.label && control.type !== 'checkbox' && <label className="mb-1.5 block text-sm font-medium text-slate-700">{control.label}</label>}
                                        <FieldRenderer
                                            field={{ ...control, name: control.id, width: 'large', description: control.type === 'checkbox' ? control.label : control.description }}
                                            value={values[control.id]}
                                            onChange={(v) => change(control.id, v)}
                                            context={{ mediaPreviews: media, onMediaSelect: (m) => setMedia((all) => ({ ...all, [m.id]: m.medium || m.url })) }}
                                        />
                                        {control.description && control.type !== 'checkbox' && <p className="mt-1 text-xs text-slate-500">{control.description}</p>}
                                        {control.refresh && <p className="mt-1 text-[11px] text-slate-400">Preview updates after publishing.</p>}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>

                <footer className="flex items-center justify-center gap-1 border-t border-slate-200 p-2">
                    {[
                        ['desktop', Monitor],
                        ['tablet', Tablet],
                        ['mobile', Smartphone],
                    ].map(([key, Icon]) => (
                        <button key={key} type="button" onClick={() => setDevice(key)} className={cx('rounded p-2', device === key ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100')} aria-label={key}>
                            <Icon className="size-4" />
                        </button>
                    ))}
                    <button type="button" onClick={() => frame.current?.contentWindow?.location.reload()} className="rounded p-2 text-slate-500 hover:bg-slate-100" aria-label="Reload preview">
                        <RefreshCw className="size-4" />
                    </button>
                </footer>
            </aside>

            <main className="flex flex-1 items-start justify-center overflow-auto p-0">
                <iframe ref={frame} src={previewUrl} title="Site preview" className="h-full border-0 bg-white shadow-sm transition-all" style={{ width: widths[device] }} />
            </main>
        </div>
    );
}
