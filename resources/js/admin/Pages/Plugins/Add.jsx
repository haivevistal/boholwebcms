import { useForm } from '@inertiajs/react';
import { FilePlus2, UploadCloud } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, Checkbox, FieldError, FormRow, Input, PageHeader, Textarea } from '../../Components/ui';

export default function AddPlugin({ maxUploadMb }) {
    const upload = useForm({ package: null, overwrite: false, activate: true });
    const create = useForm({ name: '', description: '', author: '' });

    return (
        <AdminLayout title="Add Plugin">
            <PageHeader title="Add Plugins" description="Plugins extend and expand the functionality of your site. Install one from a .zip file, or scaffold a new plugin to start developing." />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card title={<span className="flex items-center gap-2"><UploadCloud className="size-4" /> Upload Plugin</span>}>
                    <p className="mb-4 text-sm text-slate-600">If you have a plugin in a .zip format, you may install or update it by uploading it here.</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            upload.post('/admin/plugins/upload', { forceFormData: true });
                        }}
                        className="space-y-4"
                    >
                        <label
                            className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center hover:bg-slate-100"
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={(e) => {
                                e.preventDefault();
                                upload.setData('package', e.dataTransfer.files[0]);
                            }}
                        >
                            <UploadCloud className="size-8 text-slate-400" />
                            <span className="text-sm text-slate-600">{upload.data.package ? upload.data.package.name : 'Drop a .zip here or click to choose'}</span>
                            <span className="text-xs text-slate-400">Max {maxUploadMb} MB</span>
                            <input type="file" accept=".zip" className="hidden" onChange={(e) => upload.setData('package', e.target.files[0])} />
                        </label>
                        <FieldError message={upload.errors.package} />
                        <div className="flex flex-col gap-2">
                            <Checkbox label="Activate after installing" checked={upload.data.activate} onChange={(e) => upload.setData('activate', e.target.checked)} />
                            <Checkbox label="Replace the current version if this plugin is already installed" checked={upload.data.overwrite} onChange={(e) => upload.setData('overwrite', e.target.checked)} />
                        </div>
                        {upload.progress && (
                            <div className="h-2 overflow-hidden rounded bg-slate-100">
                                <div className="h-full bg-brand-600" style={{ width: `${upload.progress.percentage}%` }} />
                            </div>
                        )}
                        <Button type="submit" disabled={!upload.data.package} loading={upload.processing}>
                            Install Now
                        </Button>
                    </form>
                </Card>

                <Card title={<span className="flex items-center gap-2"><FilePlus2 className="size-4" /> Create a New Plugin</span>}>
                    <p className="mb-2 text-sm text-slate-600">
                        Generates <code className="rounded bg-slate-100 px-1">content/plugins/your-plugin/your-plugin.php</code> with a header, an activation hook, an example shortcode and an admin page — then opens it in the Plugin File Editor.
                    </p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            create.post('/admin/plugins/create');
                        }}
                    >
                        <FormRow label="Plugin name" error={create.errors.name}>
                            <Input value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} placeholder="My Awesome Plugin" />
                        </FormRow>
                        <FormRow label="Description">
                            <Textarea rows={2} value={create.data.description} onChange={(e) => create.setData('description', e.target.value)} />
                        </FormRow>
                        <FormRow label="Author">
                            <Input value={create.data.author} onChange={(e) => create.setData('author', e.target.value)} />
                        </FormRow>
                        <div className="pt-3">
                            <Button type="submit" variant="secondary" loading={create.processing}>
                                Create Plugin
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AdminLayout>
    );
}
