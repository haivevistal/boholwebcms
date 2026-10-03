import { useForm } from '@inertiajs/react';
import AuthShell from './AuthShell';
import { Button, FieldError, Input, Label } from '../../Components/ui';

export default function Register() {
    const form = useForm({ username: '', email: '', password: '', password_confirmation: '' });

    return (
        <AuthShell
            title="Registration"
            footer={
                <a href="/login" className="text-brand-600 hover:underline">
                    Already registered? Log in
                </a>
            }
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/register');
                }}
                className="space-y-4"
            >
                {[
                    ['username', 'Username', 'text'],
                    ['email', 'Email', 'email'],
                    ['password', 'Password', 'password'],
                    ['password_confirmation', 'Confirm Password', 'password'],
                ].map(([key, label, type]) => (
                    <div key={key}>
                        <Label htmlFor={key}>{label}</Label>
                        <Input id={key} type={type} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} error={form.errors[key]} />
                        <FieldError message={form.errors[key]} />
                    </div>
                ))}
                <Button type="submit" className="w-full" loading={form.processing}>
                    Register
                </Button>
            </form>
        </AuthShell>
    );
}
