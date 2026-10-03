import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import FieldRenderer from '../../Components/FieldRenderer';
import { Button, Card, FormRow, Input, PageHeader, Select, Textarea } from '../../Components/ui';

export default function UserEdit({ user, roles, defaultRole, extraFields = [], mode, canChangeRole }) {
    const isCreate = mode === 'create';
    const form = useForm({
        username: user?.username || '',
        email: user?.email || '',
        first_name: user?.first_name || '',
        last_name: user?.last_name || '',
        name: user?.name || '',
        website: user?.website || '',
        bio: user?.bio || '',
        role: user?.role || defaultRole || 'subscriber',
        password: '',
        password_confirmation: '',
        meta: Object.fromEntries(extraFields.map((f) => [f.name, f.value ?? ''])),
    });

    const submit = (e) => {
        e.preventDefault();
        if (isCreate) form.post('/admin/users');
        else if (mode === 'profile') form.put('/admin/profile', { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') });
        else form.put(`/admin/users/${user.id}`, { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') });
    };

    const title = isCreate ? 'Add User' : mode === 'profile' ? 'Profile' : `Edit User ${user.username}`;
    const nameChoices = [...new Set([user?.username, form.data.first_name, form.data.last_name, `${form.data.first_name} ${form.data.last_name}`.trim(), form.data.name].filter(Boolean))];

    return (
        <AdminLayout title={title}>
            <PageHeader title={title} description={isCreate ? 'Create a brand new user and add them to this site.' : null} />
            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <Card title="Name">
                    {isCreate ? (
                        <FormRow label="Username (required)" error={form.errors.username}>
                            <Input value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} className="max-w-md" />
                        </FormRow>
                    ) : (
                        <FormRow label="Username" description="Usernames cannot be changed.">
                            <div className="flex items-center gap-3">
                                <img src={user.avatar} alt="" className="size-12 rounded-full" />
                                <Input value={user.username} disabled className="max-w-xs" />
                            </div>
                        </FormRow>
                    )}
                    {!isCreate && (
                        <FormRow label="Role">
                            {canChangeRole ? (
                                <Select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} options={roles.map((r) => ({ value: r.slug, label: r.name }))} className="max-w-xs" />
                            ) : (
                                <span className="text-sm text-slate-700">{roles.find((r) => r.slug === user.role)?.name || user.role}</span>
                            )}
                        </FormRow>
                    )}
                    <FormRow label="First Name">
                        <Input value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} className="max-w-md" />
                    </FormRow>
                    <FormRow label="Last Name">
                        <Input value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} className="max-w-md" />
                    </FormRow>
                    {!isCreate && (
                        <FormRow label="Display name publicly as" error={form.errors.name}>
                            <Select value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} options={nameChoices.map((n) => ({ value: n, label: n }))} className="max-w-md" />
                        </FormRow>
                    )}
                </Card>

                <Card title="Contact Info">
                    <FormRow label="Email (required)" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="max-w-md" />
                    </FormRow>
                    <FormRow label="Website" error={form.errors.website}>
                        <Input type="url" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} className="max-w-md" placeholder="https://" />
                    </FormRow>
                </Card>

                {!isCreate && (
                    <Card title="About the user">
                        <FormRow label="Biographical Info" description="Share a little biographical information to fill out your profile. This may be shown publicly.">
                            <Textarea value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} rows={4} />
                        </FormRow>
                    </Card>
                )}

                {extraFields.length > 0 && (
                    <Card title="Additional Information">
                        {extraFields.map((f) => (
                            <FormRow key={f.name} label={f.label} description={f.description}>
                                <FieldRenderer field={f} value={form.data.meta[f.name]} onChange={(v) => form.setData('meta', { ...form.data.meta, [f.name]: v })} />
                            </FormRow>
                        ))}
                    </Card>
                )}

                <Card title={isCreate ? 'Account' : 'Account Management'}>
                    <FormRow label={isCreate ? 'Password (required)' : 'New Password'} error={form.errors.password} description={isCreate ? null : 'Leave blank to keep the current password.'}>
                        <div className="flex max-w-md gap-2">
                            <Input type="text" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" className="font-mono" />
                            <Button variant="secondary" onClick={() => {
                                const p = generatePassword();
                                form.setData((d) => ({ ...d, password: p, password_confirmation: p }));
                            }}>
                                Generate
                            </Button>
                        </div>
                    </FormRow>
                    {!isCreate && (
                        <FormRow label="Repeat New Password">
                            <Input type="text" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className="max-w-md font-mono" autoComplete="new-password" />
                        </FormRow>
                    )}
                    {isCreate && (
                        <FormRow label="Role" error={form.errors.role}>
                            <Select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} options={roles.map((r) => ({ value: r.slug, label: r.name }))} className="max-w-xs" />
                        </FormRow>
                    )}
                </Card>

                <Button type="submit" loading={form.processing}>
                    {isCreate ? 'Add User' : mode === 'profile' ? 'Update Profile' : 'Update User'}
                </Button>
            </form>
        </AdminLayout>
    );
}

function generatePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    const bytes = crypto.getRandomValues(new Uint32Array(20));
    return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}
