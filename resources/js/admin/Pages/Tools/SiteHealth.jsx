import { CheckCircle2, CircleAlert, TriangleAlert } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Card, PageHeader } from '../../Components/ui';

export default function SiteHealth({ checks, info }) {
    const failing = checks.filter((c) => !c.ok);
    const score = Math.round(((checks.length - failing.length) / Math.max(1, checks.length)) * 100);

    return (
        <AdminLayout title="Site Health">
            <PageHeader title="Site Health" />
            <div className="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <Card title={`Status — ${score >= 80 ? 'Good' : 'Should be improved'} (${score}%)`}>
                    <div className="mb-5 h-2 overflow-hidden rounded bg-slate-100">
                        <div className={score >= 80 ? 'h-full bg-emerald-500' : 'h-full bg-amber-500'} style={{ width: `${score}%` }} />
                    </div>
                    <ul className="divide-y divide-slate-100">
                        {checks.map((c) => (
                            <li key={c.label} className="flex gap-3 py-3 text-sm">
                                {c.ok ? (
                                    <CheckCircle2 className="size-5 shrink-0 text-emerald-500" />
                                ) : c.severity === 'critical' ? (
                                    <CircleAlert className="size-5 shrink-0 text-red-500" />
                                ) : (
                                    <TriangleAlert className="size-5 shrink-0 text-amber-500" />
                                )}
                                <div>
                                    <p className="font-medium text-slate-800">{c.label}</p>
                                    <p className="text-slate-600">{c.detail}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
                <div className="space-y-6">
                    {Object.entries(info).map(([section, rows]) => (
                        <Card key={section} title={section} bodyClassName="p-0">
                            <table className="w-full text-sm">
                                <tbody className="divide-y divide-slate-100">
                                    {Object.entries(rows).map(([k, v]) => (
                                        <tr key={k}>
                                            <th className="w-1/2 px-4 py-2 text-left font-medium text-slate-600">{k}</th>
                                            <td className="break-all px-4 py-2 font-mono text-xs text-slate-800">{String(v)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
