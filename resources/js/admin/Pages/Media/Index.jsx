import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import axios from 'axios';
import { Check, FileText, Images, UploadCloud } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, cx, EmptyState, Input, Modal, Pagination, SearchBox, Select, Textarea } from '../../Components/ui';

export default function MediaIndex({ media, filters, months, maxUploadKb, allowed }) {
    const [selectMode, setSelectMode] = useState(false);
    const [selected, setSelected] = useState([]);
    const [details, setDetails] = useState(null);
    const [uploads, setUploads] = useState([]);
    const [showUploader, setShowUploader] = useState(false);
    const fileInput = useRef(null);

    const visit = (params) => router.get('/admin/media', { ...filters, ...params }, { preserveState: true, preserveScroll: true });

    const upload = async (files) => {
        for (const file of files) {
            const id = `${file.name}-${Date.now()}`;
            setUploads((u) => [...u, { id, name: file.name, progress: 0 }]);
            const form = new FormData();
            form.append('file', file);
            try {
                await axios.post('/admin/media', form, {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (e) => setUploads((u) => u.map((x) => (x.id === id ? { ...x, progress: Math.round((e.loaded / (e.total || 1)) * 100) } : x))),
                });
                setUploads((u) => u.filter((x) => x.id !== id));
            } catch (e) {
                setUploads((u) => u.map((x) => (x.id === id ? { ...x, error: e.response?.data?.message || 'Upload failed' } : x)));
            }
        }
        router.reload({ only: ['media', 'months'] });
    };

    return (
        <AdminLayout title="Media Library">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Media Library</h1>
                <Button variant="secondary" size="sm" onClick={() => setShowUploader((s) => !s)}>
                    Add Media File
                </Button>
            </div>

            {showUploader && (
                <div
                    className="mb-5 flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-white py-10 text-center"
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        upload([...e.dataTransfer.files]);
                    }}
                >
                    <UploadCloud className="size-10 text-slate-400" />
                    <p className="text-sm text-slate-600">Drop files to upload</p>
                    <Button variant="secondary" onClick={() => fileInput.current?.click()}>
                        Select Files
                    </Button>
                    <p className="text-xs text-slate-500">
                        Maximum upload file size: {Math.round(maxUploadKb / 1024)} MB. Allowed: {allowed.replaceAll(',', ', ')}
                    </p>
                    <input ref={fileInput} type="file" multiple className="hidden" onChange={(e) => upload([...e.target.files])} />
                </div>
            )}

            {uploads.length > 0 && (
                <div className="mb-4 space-y-2">
                    {uploads.map((u) => (
                        <div key={u.id} className="rounded border border-slate-200 bg-white px-3 py-2 text-sm">
                            <div className="flex justify-between">
                                <span className="truncate">{u.name}</span>
                                <span className={u.error ? 'text-red-600' : 'text-slate-500'}>{u.error || `${u.progress}%`}</span>
                            </div>
                            {!u.error && (
                                <div className="mt-1 h-1.5 overflow-hidden rounded bg-slate-100">
                                    <div className="h-full bg-brand-600" style={{ width: `${u.progress}%` }} />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                <Select value={filters.type || ''} onChange={(e) => visit({ type: e.target.value || undefined, page: undefined })} className="w-44" options={{ '': 'All media items', image: 'Images', video: 'Video', audio: 'Audio', document: 'Documents' }} />
                <Select value={filters.m || ''} onChange={(e) => visit({ m: e.target.value || undefined, page: undefined })} className="w-40" placeholder="All dates" options={months.map((m) => ({ value: m, label: m }))} />
                <Button variant={selectMode ? 'primary' : 'secondary'} onClick={() => (setSelectMode((s) => !s), setSelected([]))}>
                    {selectMode ? 'Cancel' : 'Bulk select'}
                </Button>
                {selectMode && (
                    <Button
                        variant="danger"
                        disabled={!selected.length}
                        onClick={() => window.confirm(`Delete ${selected.length} file(s) permanently?`) && router.post('/admin/media/bulk-delete', { ids: selected }, { onSuccess: () => (setSelected([]), setSelectMode(false)) })}
                    >
                        Delete permanently
                    </Button>
                )}
                <div className="ml-auto">
                    <SearchBox value={filters.s} onSearch={(s) => visit({ s, page: undefined })} placeholder="Search media" />
                </div>
            </div>

            {media.data.length ? (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 2xl:grid-cols-8">
                    {media.data.map((m) => {
                        const isSel = selected.includes(m.id);
                        return (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => (selectMode ? setSelected((s) => (isSel ? s.filter((x) => x !== m.id) : [...s, m.id])) : setDetails(m))}
                                className={cx('group relative aspect-square overflow-hidden rounded-lg border-2 bg-white shadow-sm', isSel ? 'border-brand-600' : 'border-transparent hover:border-slate-300')}
                            >
                                {m.is_image ? (
                                    <img src={m.thumb || m.url} alt={m.alt || ''} className="size-full object-cover" loading="lazy" />
                                ) : (
                                    <span className="flex size-full flex-col items-center justify-center gap-2 p-3 text-xs text-slate-500">
                                        <FileText className="size-8" />
                                        <span className="line-clamp-2 break-all">{m.filename}</span>
                                    </span>
                                )}
                                {isSel && (
                                    <span className="absolute right-1.5 top-1.5 grid size-6 place-items-center rounded-full bg-brand-600 text-white">
                                        <Check className="size-4" />
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            ) : (
                <Card>
                    <EmptyState icon={Images} title="No media files found." />
                </Card>
            )}
            <div className="mt-4">
                <Pagination meta={media} />
            </div>

            {details && <Details item={details} onClose={() => setDetails(null)} />}
        </AdminLayout>
    );
}

function Details({ item, onClose }) {
    const [data, setData] = useState({ title: item.title || '', alt: item.alt || '', caption: item.caption || '', description: item.description || '' });
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(`/admin/media/${item.id}`, data, { preserveScroll: true, onFinish: () => setSaving(false), onSuccess: onClose });
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="lg"
            title="Attachment details"
            footer={
                <>
                    <Button variant="danger-ghost" className="mr-auto" onClick={() => window.confirm('Delete this file permanently?') && router.delete(`/admin/media/${item.id}`, { onSuccess: onClose })}>
                        Delete permanently
                    </Button>
                    <Button variant="secondary" onClick={onClose}>
                        Close
                    </Button>
                    <Button onClick={save} loading={saving}>
                        Save
                    </Button>
                </>
            }
        >
            <div className="grid gap-6 md:grid-cols-[1fr_300px]">
                <div className="flex items-center justify-center rounded-lg bg-slate-100 p-3">
                    {item.is_image ? (
                        <img src={item.sizes?.large || item.url} alt="" className="max-h-[60vh] rounded" />
                    ) : item.mime_type.startsWith('video/') ? (
                        <video src={item.url} controls className="max-h-[60vh] w-full" />
                    ) : item.mime_type.startsWith('audio/') ? (
                        <audio src={item.url} controls className="w-full" />
                    ) : (
                        <FileText className="size-16 text-slate-400" />
                    )}
                </div>
                <div className="space-y-3 text-sm">
                    <dl className="space-y-1 text-xs text-slate-600">
                        <div><strong>Uploaded on:</strong> {item.date}</div>
                        {item.uploaded_by && <div><strong>Uploaded by:</strong> {item.uploaded_by}</div>}
                        <div className="break-all"><strong>File name:</strong> {item.filename}</div>
                        <div><strong>File type:</strong> {item.mime_type}</div>
                        <div><strong>File size:</strong> {item.size_human}</div>
                        {item.width && <div><strong>Dimensions:</strong> {item.width} by {item.height} pixels</div>}
                    </dl>
                    {item.is_image && (
                        <label className="block text-xs font-medium text-slate-600">
                            Alternative Text
                            <Input className="mt-1" value={data.alt} onChange={(e) => setData({ ...data, alt: e.target.value })} />
                        </label>
                    )}
                    <label className="block text-xs font-medium text-slate-600">
                        Title
                        <Input className="mt-1" value={data.title} onChange={(e) => setData({ ...data, title: e.target.value })} />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        Caption
                        <Textarea className="mt-1" rows={2} value={data.caption} onChange={(e) => setData({ ...data, caption: e.target.value })} />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        Description
                        <Textarea className="mt-1" rows={2} value={data.description} onChange={(e) => setData({ ...data, description: e.target.value })} />
                    </label>
                    <label className="block text-xs font-medium text-slate-600">
                        File URL
                        <div className="mt-1 flex gap-2">
                            <Input readOnly value={item.url} onFocus={(e) => e.target.select()} className="text-xs" />
                            <Button variant="secondary" size="sm" onClick={() => navigator.clipboard?.writeText(item.url)}>
                                Copy
                            </Button>
                        </div>
                    </label>
                </div>
            </div>
        </Modal>
    );
}
