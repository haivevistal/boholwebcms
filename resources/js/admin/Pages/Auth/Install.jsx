import AuthShell from './AuthShell';

export default function Install() {
    return (
        <AuthShell title="Install">
            <h1 className="mb-2 text-lg font-semibold">Welcome!</h1>
            <p className="mb-4 text-sm text-slate-600">This site isn’t installed yet. Run the installer from your project folder:</p>
            <pre className="rounded-md bg-slate-900 p-3 text-xs text-slate-100">php artisan cms:install</pre>
            <p className="mt-4 text-xs text-slate-500">
                Add <code>--demo</code> to also activate the Simple Shop example plugin. Then reload this page.
            </p>
        </AuthShell>
    );
}
