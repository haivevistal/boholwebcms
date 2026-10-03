import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Button, Card, Checkbox, PageHeader } from '../../Components/ui';

export default function Export({ types }) {
    const [selected, setSelected] = useState(types.map((t) => t.name));
    const [settings, setSettings] = useState(true);
    const [comments, setComments] = useState(true);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    return (
        <AdminLayout title="Export">
            <PageHeader title="Export" description="When you click the button below a JSON file is created for you to save. It can be imported into another BoholwebCMS site with Tools → Import." />
            <Card className="max-w-2xl">
                {/* A native form so the browser handles the file download. */}
                <form method="post" action="/admin/tools/export" className="space-y-5">
                    <input type="hidden" name="_token" value={csrf} />
                    <fieldset>
                        <legend className="mb-2 text-sm font-semibold text-slate-800">Choose what to export</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {types.map((t) => (
                                <Checkbox
                                    key={t.name}
                                    name="types[]"
                                    value={t.name}
                                    label={`${t.label} (${t.count})`}
                                    checked={selected.includes(t.name)}
                                    onChange={(e) => setSelected((s) => (e.target.checked ? [...s, t.name] : s.filter((x) => x !== t.name)))}
                                />
                            ))}
                        </div>
                    </fieldset>
                    <div className="flex flex-col gap-2">
                        <input type="hidden" name="include_comments" value={comments ? 1 : 0} />
                        <input type="hidden" name="include_settings" value={settings ? 1 : 0} />
                        <Checkbox label="Include comments" checked={comments} onChange={(e) => setComments(e.target.checked)} />
                        <Checkbox label="Include settings (options)" checked={settings} onChange={(e) => setSettings(e.target.checked)} />
                    </div>
                    <Button type="submit" disabled={!selected.length}>
                        Download Export File
                    </Button>
                </form>
            </Card>
        </AdminLayout>
    );
}
