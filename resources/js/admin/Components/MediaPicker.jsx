import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Check, FileText, UploadCloud } from 'lucide-react';
import { Button, cx, Input, Modal, Spinner } from './ui';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Media library modal used by the editor, featured image, settings & customizer.
 *   <MediaPicker open onClose onSelect={(media) => ...} type="image" multiple />
 */
export default function MediaPicker({ open, onClose, onSelect, type = '', multiple = false, title = 'Select media' }) {
    const [tab, setTab] = useState('library');
    const [items, setItems] = useState([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState([]);
    const [uploading, setUploading] = useState(null);
    const [error, setError] = useState(null);
    const fileInput = useRef(null);

    const load = useCallback(
        (p = 1, q = search) => {
            setLoading(true);
            axios
                .get('/admin/media/json', { params: { page: p, s: q, type } })
                .then(({ data }) => {
                    setItems((prev) => (p === 1 ? data.data : [...prev, ...data.data]));
                    setPage(data.current_page);
                    setLastPage(data.last_page);
                })
                .finally(() => setLoading(false));
        },
        [search, type],
    );

    useEffect(() => {
        if (open) {
            setSelected([]);
            setError(null);
            load(1, '');
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const upload = async (files) => {
        setError(null);
        const uploaded = [];
        for (const file of files) {
            const form = new FormData();
            form.append('file', file);
            try {
                setUploading({ name: file.name, progress: 0 });
                const { data } = await axios.post('/admin/media', form, {
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    onUploadProgress: (e) => setUploading({ name: file.name, progress: Math.round((e.loaded / (e.total || 1)) * 100) }),
                });
                uploaded.push(data);
            } catch (e) {
                setError(e.response?.data?.message || `Upload of ${file.name} failed.`);
            }
        }
        setUploading(null);
        if (uploaded.length) {
            setItems((prev) => [...uploaded, ...prev]);
            setSelected(multiple ? uploaded : [uploaded[0]]);
            setTab('library');
        }
    };

    const toggle = (item) => {
        if (multiple) {
            setSelected((s) => (s.some((x) => x.id === item.id) ? s.filter((x) => x.id !== item.id) : [...s, item]));
        } else {
            setSelected([item]);
        }
    };

    const current = selected[selected.length - 1];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="xl"
            footer={
                <>
                    <span className="mr-auto self-center text-sm text-slate-500">{selected.length ? `${selected.length} selected` : ''}</span>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button disabled={!selected.length} onClick={() => onSelect(multiple ? selected : selected[0])}>
                        {multiple ? 'Insert selected' : 'Select'}
                    </Button>
                </>
            }
        >
            <div className="mb-4 flex items-center gap-4 border-b border-slate-200">
                {['library', 'upload'].map((t) => (
                    <button
                        key={t}
                        type="button"
                        className={cx('-mb-px border-b-2 px-1 pb-2 text-sm font-medium', tab === t ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700')}
                        onClick={() => setTab(t)}
                    >
                        {t === 'library' ? 'Media Library' : 'Upload Files'}
                    </button>
                ))}
            </div>

            {error && <p className="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

            {tab === 'upload' ? (
                <div
                    className="flex h-72 flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 text-center"
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        upload([...e.dataTransfer.files]);
                    }}
                >
                    <UploadCloud className="size-10 text-slate-400" />
                    {uploading ? (
                        <div className="w-64">
                            <p className="mb-2 truncate text-sm text-slate-600">Uploading {uploading.name}…</p>
                            <div className="h-2 overflow-hidden rounded bg-slate-200">
                                <div className="h-full bg-brand-600 transition-all" style={{ width: `${uploading.progress}%` }} />
                            </div>
                        </div>
                    ) : (
                        <>
                            <p className="text-sm text-slate-600">Drop files to upload, or</p>
                            <Button variant="secondary" onClick={() => fileInput.current?.click()}>
                                Select Files
                            </Button>
                        </>
                    )}
                    <input ref={fileInput} type="file" multiple className="hidden" accept={type === 'image' ? 'image/*' : undefined} onChange={(e) => upload([...e.target.files])} />
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-[1fr_260px]">
                    <div>
                        <form
                            className="mb-3 flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                load(1, search);
                            }}
                        >
                            <Input type="search" placeholder="Search media…" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
                        </form>
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            {items.map((item) => {
                                const isSel = selected.some((s) => s.id === item.id);
                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => toggle(item)}
                                        onDoubleClick={() => onSelect(multiple ? [item] : item)}
                                        className={cx('relative aspect-square overflow-hidden rounded-md border-2 bg-slate-100', isSel ? 'border-brand-600 ring-2 ring-brand-500/40' : 'border-transparent hover:border-slate-300')}
                                    >
                                        {item.is_image ? (
                                            <img src={item.thumb || item.url} alt={item.alt || ''} className="size-full object-cover" loading="lazy" />
                                        ) : (
                                            <span className="flex size-full flex-col items-center justify-center gap-1 p-2 text-xs text-slate-500">
                                                <FileText className="size-6" />
                                                <span className="line-clamp-2 break-all">{item.filename}</span>
                                            </span>
                                        )}
                                        {isSel && (
                                            <span className="absolute right-1 top-1 grid size-5 place-items-center rounded-full bg-brand-600 text-white">
                                                <Check className="size-3.5" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        {loading && (
                            <div className="flex justify-center py-6">
                                <Spinner />
                            </div>
                        )}
                        {!loading && !items.length && <p className="py-10 text-center text-sm text-slate-500">No media found. Upload some files first.</p>}
                        {!loading && page < lastPage && (
                            <div className="mt-4 text-center">
                                <Button variant="secondary" onClick={() => load(page + 1)}>
                                    Load more
                                </Button>
                            </div>
                        )}
                    </div>
                    <aside className="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                        {current ? <Details item={current} onChange={(updated) => setItems((all) => all.map((i) => (i.id === updated.id ? updated : i)))} /> : <p className="text-slate-500">Select a file to see its details.</p>}
                    </aside>
                </div>
            )}
        </Modal>
    );
}

function Details({ item, onChange }) {
    const [alt, setAlt] = useState(item.alt || '');
    const [title, setTitle] = useState(item.title || '');
    useEffect(() => {
        setAlt(item.alt || '');
        setTitle(item.title || '');
    }, [item.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const save = () =>
        axios.put(`/admin/media/${item.id}`, { alt, title }, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).then(({ data }) => onChange(data));

    return (
        <div className="space-y-3">
            {item.is_image && <img src={item.medium || item.url} alt="" className="w-full rounded border border-slate-200 bg-white" />}
            <div className="text-xs text-slate-500">
                <p className="break-all font-medium text-slate-700">{item.filename}</p>
                <p>{item.date}</p>
                <p>
                    {item.size_human}
                    {item.width ? ` · ${item.width}×${item.height}` : ''}
                </p>
            </div>
            <label className="block text-xs font-medium text-slate-600">
                Alt text
                <Input value={alt} onChange={(e) => setAlt(e.target.value)} onBlur={save} className="mt-1" />
            </label>
            <label className="block text-xs font-medium text-slate-600">
                Title
                <Input value={title} onChange={(e) => setTitle(e.target.value)} onBlur={save} className="mt-1" />
            </label>
            <label className="block text-xs font-medium text-slate-600">
                File URL
                <Input readOnly value={item.url} onFocus={(e) => e.target.select()} className="mt-1 text-xs" />
            </label>
        </div>
    );
}
