import { useForm } from '@inertiajs/react';
import AuthShell from './AuthShell';
import { Button, Checkbox, FieldError, Input, Label } from '../../Components/ui';

export default function Login({ canRegister, redirectTo, loginMessage }) {
    const form = useForm({ login: '', password: '', remember: false, redirect_to: redirectTo || '' });

    const submit = (e) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <AuthShell
            title="Log In"
            footer={
                <>
                    {canRegister && (
                        <a href="/register" className="text-brand-600 hover:underline">
                            Register
                        </a>
                    )}
                    {canRegister && <span className="mx-2 text-slate-300">|</span>}
                    <a href="/" className="text-slate-500 hover:underline">
                        ← Go to site
                    </a>
                </>
            }
        >
            {loginMessage && <p className="mb-4 rounded bg-brand-50 px-3 py-2 text-sm text-brand-700">{loginMessage}</p>}
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="login">Username or Email Address</Label>
                    <Input id="login" autoFocus autoComplete="username" value={form.data.login} onChange={(e) => form.setData('login', e.target.value)} error={form.errors.login} />
                    <FieldError message={form.errors.login} />
                </div>
                <div>
                    <Label htmlFor="password">Password</Label>
                    <Input id="password" type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    <FieldError message={form.errors.password} />
                </div>
                <div className="flex items-center justify-between">
                    <Checkbox label="Remember Me" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />
                    <Button type="submit" loading={form.processing}>
                        Log In
                    </Button>
                </div>
            </form>
        </AuthShell>
    );
}
