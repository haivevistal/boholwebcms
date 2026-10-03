import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import {
    AlertTriangle, ArrowLeft, ArrowRight, Check, CheckCircle2, Circle, Database, Eye, EyeOff, Globe, Loader2, Lock, PartyPopper, RefreshCw, Server, Sparkles, XCircle,
} from 'lucide-react';
import { Button, Checkbox, cx, FieldError, Input, Select } from '../../Components/ui';

const STEPS = [
    { key: 'welcome', title: 'Welcome', subtitle: 'Server check', icon: Server },
    { key: 'database', title: 'Database', subtitle: 'Connection details', icon: Database },
    { key: 'site', title: 'Site', subtitle: 'Title & admin account', icon: Globe },
    { key: 'install', title: 'Install', subtitle: 'Finish setup', icon: Sparkles },
];

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const post = (url, data) => axios.post(url, data, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });

export default function Wizard({ requirements, drivers, defaults, timezones, themes, demoAvailable, cms }) {
    const [step, setStep] = useState(0);
    const firstDriver = drivers.find((d) => d.value === defaults.db.driver && d.available) || drivers.find((d) => d.available) || drivers[0];

    const [db, setDb] = useState({
        driver: firstDriver.value,
        host: defaults.db.host,
        port: firstDriver.port || '',
        database: firstDriver.value === 'sqlite' ? defaults.sqlite_path : defaults.db.database,
        username: defaults.db.username,
        password: '',
        prefix: defaults.db.prefix,
        create: false,
    });
    const [dbResult, setDbResult] = useState(null);
    const [dbErrors, setDbErrors] = useState({});
    const [testing, setTesting] = useState(false);

    const [site, setSite] = useState({
        title: '',
        description: '',
        url: defaults.url,
        admin_username: 'admin',
        admin_email: '',
        admin_password: generatePassword(),
        timezone: guessTimezone(timezones, defaults.timezone),
        theme: themes[0]?.slug || 'aurora',
        sample_content: true,
        demo: false,
        discourage_search_engines: false,
        production: !/localhost|127\.0\.0\.1|\.test$/.test(new URL(defaults.url).hostname),
    });
    const [siteErrors, setSiteErrors] = useState({});

    const [install, setInstall] = useState({ running: false, log: [], error: null, done: null });

    const dbReady = dbResult && (dbResult.ok || (dbResult.can_create && db.create));

    const changeDb = (patch) => {
        setDb((d) => ({ ...d, ...patch }));
        setDbResult(null);
        setDbErrors((e) => Object.fromEntries(Object.entries(e).filter(([k]) => !(k in patch))));
    };

    const testDatabase = async () => {
        setTesting(true);
        setDbErrors({});
        try {
            const { data } = await post('/install/database', { database: db });
            setDbResult(data);
        } catch (e) {
            if (e.response?.status === 422) setDbErrors(flattenErrors(e.response.data.errors, 'database.'));
            else setDbResult({ ok: false, message: e.response?.data?.message || 'Request failed — check the server error log.' });
        } finally {
            setTesting(false);
        }
    };

    const validateSite = () => {
        const errors = {};
        if (!site.title.trim()) errors.title = 'Please enter a site title.';
        if (!/^https?:\/\/.+/.test(site.url)) errors.url = 'Enter the full address, starting with http:// or https://';
        if (!/^[A-Za-z0-9_-]{3,}$/.test(site.admin_username)) errors.admin_username = 'At least 3 characters: letters, numbers, dashes and underscores.';
        if (site.admin_password.length < 8) errors.admin_password = 'Use at least 8 characters.';
        if (!/^\S+@\S+\.\S+$/.test(site.admin_email)) errors.admin_email = 'Please enter a valid email address.';
        setSiteErrors(errors);
        return !Object.keys(errors).length;
    };

    const runInstall = async () => {
        setStep(3);
        setInstall({ running: true, log: [], error: null, done: null });
        try {
            const { data } = await post('/install/run', { database: db, site });
            await reveal(data.log, (log) => setInstall((s) => ({ ...s, log })));
            setInstall((s) => ({ ...s, running: false, done: data }));
        } catch (e) {
            const data = e.response?.data || {};
            if (e.response?.status === 422 && data.errors) {
                const siteErrs = flattenErrors(data.errors, 'site.');
                const dbErrs = flattenErrors(data.errors, 'database.');
                setSiteErrors(siteErrs);
                setDbErrors(dbErrs);
                setInstall({ running: false, log: [], error: null, done: null });
                setStep(Object.keys(dbErrs).length ? 1 : 2);
                return;
            }
            setInstall({ running: false, log: data.log || [], error: data.message || 'The installation failed. Check storage/logs/laravel.log for details.', done: null });
        }
    };

    return (
        <div className="min-h-screen bg-slate-100 lg:grid lg:grid-cols-[340px_1fr]">
            <Head title={`Install ${cms.name}`} />

            {/* ----- Left rail ----- */}
            <aside className="relative overflow-hidden bg-slate-950 px-8 py-10 text-white lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
                <div className="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-brand-600/40 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-32 -right-20 size-72 rounded-full bg-fuchsia-600/30 blur-3xl" />
                <div className="relative">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-fuchsia-500 text-xl font-extrabold shadow-lg">B</span>
                        <div>
                            <p className="text-lg font-semibold leading-tight">{cms.name}</p>
                            <p className="text-xs text-slate-400">Version {cms.version}</p>
                        </div>
                    </div>
                    <h1 className="mt-10 text-2xl font-semibold leading-snug">Let’s set up your new site</h1>
                    <p className="mt-2 text-sm text-slate-400">Four quick steps. You’ll need your database name, username and password from your hosting provider.</p>

                    <ol className="mt-10 space-y-1">
                        {STEPS.map((s, i) => {
                            const state = i < step || install.done ? 'done' : i === step ? 'current' : 'todo';
                            const Icon = s.icon;
                            return (
                                <li key={s.key} className={cx('flex items-center gap-4 rounded-xl px-3 py-3 transition', state === 'current' && 'bg-white/10')}>
                                    <span
                                        className={cx(
                                            'grid size-9 shrink-0 place-items-center rounded-full border',
                                            state === 'done' && 'border-emerald-400 bg-emerald-400 text-slate-950',
                                            state === 'current' && 'border-brand-400 bg-brand-500 text-white',
                                            state === 'todo' && 'border-slate-700 text-slate-500',
                                        )}
                                    >
                                        {state === 'done' ? <Check className="size-4" /> : <Icon className="size-4" />}
                                    </span>
                                    <span>
                                        <span className={cx('block text-sm font-medium', state === 'todo' ? 'text-slate-400' : 'text-white')}>{s.title}</span>
                                        <span className="block text-xs text-slate-500">{s.subtitle}</span>
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            </aside>

            {/* ----- Content ----- */}
            <main className="px-4 py-8 sm:px-8 lg:px-14 lg:py-14">
                <div className="mx-auto max-w-3xl">
                    {step === 0 && <WelcomeStep requirements={requirements} cms={cms} onNext={() => setStep(1)} />}

                    {step === 1 && (
                        <DatabaseStep
                            drivers={drivers}
                            db={db}
                            changeDb={changeDb}
                            setDb={setDb}
                            result={dbResult}
                            errors={dbErrors}
                            testing={testing}
                            onTest={testDatabase}
                            ready={dbReady}
                            sqlitePath={defaults.sqlite_path}
                            onBack={() => setStep(0)}
                            onNext={() => setStep(2)}
                        />
                    )}

                    {step === 2 && (
                        <SiteStep
                            site={site}
                            setSite={setSite}
                            errors={siteErrors}
                            clearError={(keys) => setSiteErrors((e) => Object.fromEntries(Object.entries(e).filter(([k]) => !keys.includes(k))))}
                            timezones={timezones}
                            themes={themes}
                            demoAvailable={demoAvailable}
                            onBack={() => setStep(1)}
                            onNext={() => validateSite() && runInstall()}
                        />
                    )}

                    {step === 3 && <InstallStep install={install} site={site} onRetry={runInstall} onBack={() => setStep(2)} />}
                </div>
            </main>
        </div>
    );
}

/* =====================================================================
 | Step 1 — welcome & requirements
 * =================================================================== */

function WelcomeStep({ requirements, cms, onNext }) {
    return (
        <StepCard
            eyebrow="Step 1 of 4"
            title={`Welcome to ${cms.name}`}
            description="Before we begin, here’s a quick check of your server. Items marked as required must pass; the others are recommended."
            footer={
                <>
                    <span className={cx('flex items-center gap-2 text-sm', requirements.passes ? 'text-emerald-700' : 'text-red-600')}>
                        {requirements.passes ? <CheckCircle2 className="size-4" /> : <XCircle className="size-4" />}
                        {requirements.passes ? 'Your server is ready.' : 'Fix the required items, then reload this page.'}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="secondary" icon={RefreshCw} onClick={() => window.location.reload()}>
                            Re-check
                        </Button>
                        <Button onClick={onNext} disabled={!requirements.passes}>
                            Let’s go <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </>
            }
        >
            <div className="grid gap-5 md:grid-cols-2">
                {Object.entries(requirements.groups).map(([group, items]) => (
                    <section key={group} className="rounded-xl border border-slate-200 p-4">
                        <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{group}</h3>
                        <ul className="space-y-2">
                            {items.map((item) => (
                                <li key={item.label} className="flex items-start gap-2.5 text-sm">
                                    {item.ok ? (
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                    ) : item.required ? (
                                        <XCircle className="mt-0.5 size-4 shrink-0 text-red-500" />
                                    ) : (
                                        <Circle className="mt-0.5 size-4 shrink-0 text-slate-300" />
                                    )}
                                    <span>
                                        <span className={cx('font-medium', item.ok ? 'text-slate-800' : item.required ? 'text-red-700' : 'text-slate-500')}>{item.label}</span>
                                        {!item.required && !item.ok && <span className="ml-1.5 text-xs text-slate-400">optional</span>}
                                        {item.detail && <span className="block text-xs text-slate-500">{item.detail}</span>}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </StepCard>
    );
}

/* =====================================================================
 | Step 2 — database
 * =================================================================== */

function DatabaseStep({ drivers, db, changeDb, setDb, result, errors, testing, onTest, ready, sqlitePath, onBack, onNext }) {
    const [showPassword, setShowPassword] = useState(false);
    const isSqlite = db.driver === 'sqlite';

    return (
        <StepCard
            eyebrow="Step 2 of 4"
            title="Database connection"
            description="Enter the database details from your hosting control panel (cPanel, Plesk, DirectAdmin, Forge…). If you’re not sure, ask your host."
            footer={
                <>
                    <Button variant="ghost" onClick={onBack}>
                        <ArrowLeft className="size-4" /> Back
                    </Button>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={onTest} loading={testing} icon={testing ? null : Database}>
                            Test connection
                        </Button>
                        <Button onClick={onNext} disabled={!ready}>
                            Continue <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </>
            }
        >
            <fieldset>
                <legend className="mb-2 text-sm font-medium text-slate-700">Database type</legend>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                    {drivers.map((d) => (
                        <button
                            key={d.value}
                            type="button"
                            disabled={!d.available}
                            title={d.available ? d.label : `Requires the PHP extension ${d.extension}`}
                            onClick={() =>
                                changeDb({
                                    driver: d.value,
                                    port: d.port || '',
                                    database: d.value === 'sqlite' ? sqlitePath : db.driver === 'sqlite' ? '' : db.database,
                                })
                            }
                            className={cx(
                                'rounded-xl border-2 px-3 py-3 text-left transition',
                                db.driver === d.value ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white hover:border-slate-300',
                                !d.available && 'cursor-not-allowed opacity-40',
                            )}
                        >
                            <span className="block text-sm font-semibold text-slate-800">{d.label}</span>
                            <span className="block text-[11px] text-slate-500">{d.available ? (d.port ? `Port ${d.port}` : 'Single file') : 'Not available'}</span>
                        </button>
                    ))}
                </div>
            </fieldset>

            {isSqlite ? (
                <div className="mt-6 grid gap-4">
                    <Field label="Database file" hint="Path relative to the site folder, or an absolute path. The file is created if it doesn’t exist." error={errors.database}>
                        <Input value={db.database} onChange={(e) => changeDb({ database: e.target.value })} className="font-mono" />
                    </Field>
                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">SQLite is great for small sites and testing. For busy production sites, MySQL, MariaDB or PostgreSQL are recommended.</p>
                </div>
            ) : (
                <div className="mt-6 grid gap-4 sm:grid-cols-6">
                    <Field className="sm:col-span-4" label="Database host" hint="Usually “localhost”." error={errors.host}>
                        <Input value={db.host} onChange={(e) => changeDb({ host: e.target.value })} placeholder="localhost" />
                    </Field>
                    <Field className="sm:col-span-2" label="Port" error={errors.port}>
                        <Input type="number" value={db.port} onChange={(e) => changeDb({ port: e.target.value })} />
                    </Field>
                    <Field className="sm:col-span-6" label="Database name" hint="The database you want to install BoholwebCMS in." error={errors.database}>
                        <Input value={db.database} onChange={(e) => changeDb({ database: e.target.value })} placeholder="boholwebcms" />
                    </Field>
                    <Field className="sm:col-span-3" label="Username" error={errors.username}>
                        <Input value={db.username} onChange={(e) => changeDb({ username: e.target.value })} autoComplete="off" />
                    </Field>
                    <Field className="sm:col-span-3" label="Password" error={errors.password}>
                        <div className="relative">
                            <Input type={showPassword ? 'text' : 'password'} value={db.password} onChange={(e) => changeDb({ password: e.target.value })} autoComplete="new-password" className="pr-10" />
                            <button type="button" onClick={() => setShowPassword((s) => !s)} className="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" aria-label="Show password">
                                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                    </Field>
                </div>
            )}

            <div className="mt-4 grid gap-4 sm:grid-cols-6">
                <Field className="sm:col-span-3" label="Table prefix" hint="Run several sites in one database by giving each a unique prefix." error={errors.prefix}>
                    <Input value={db.prefix} onChange={(e) => changeDb({ prefix: e.target.value })} className="font-mono" placeholder="bw_" />
                </Field>
            </div>

            {result && (
                <div
                    className={cx(
                        'mt-6 rounded-xl border px-4 py-3 text-sm',
                        result.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : result.can_create ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-800',
                    )}
                >
                    <p className="flex items-start gap-2 font-medium">
                        {result.ok ? <CheckCircle2 className="mt-0.5 size-4 shrink-0" /> : result.can_create ? <AlertTriangle className="mt-0.5 size-4 shrink-0" /> : <XCircle className="mt-0.5 size-4 shrink-0" />}
                        {result.message}
                    </p>
                    {result.warning && <p className="mt-1.5 pl-6 text-xs">{result.warning}</p>}
                    {result.can_create && !result.ok && (
                        <div className="mt-3 pl-6">
                            <Checkbox label={<strong>Create the database “{db.database}” for me</strong>} checked={db.create} onChange={(e) => setDb((d) => ({ ...d, create: e.target.checked }))} />
                        </div>
                    )}
                </div>
            )}
            {!result && <p className="mt-6 text-xs text-slate-500">Click “Test connection” to check the details before continuing.</p>}
        </StepCard>
    );
}

/* =====================================================================
 | Step 3 — site information
 * =================================================================== */

function SiteStep({ site, setSite, errors, clearError, timezones, themes, demoAvailable, onBack, onNext }) {
    const [showPassword, setShowPassword] = useState(true);
    const strength = useMemo(() => passwordStrength(site.admin_password), [site.admin_password]);
    const set = (patch) => {
        setSite((s) => ({ ...s, ...patch }));
        clearError(Object.keys(patch));
    };

    return (
        <StepCard
            eyebrow="Step 3 of 4"
            title="Information needed"
            description="Don’t worry — you can change all of this later in Settings."
            footer={
                <>
                    <Button variant="ghost" onClick={onBack}>
                        <ArrowLeft className="size-4" /> Back
                    </Button>
                    <Button onClick={onNext} size="lg">
                        Install {`BoholwebCMS`} <ArrowRight className="size-4" />
                    </Button>
                </>
            }
        >
            <Section title="Your site">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Site title" error={errors.title} className="sm:col-span-2">
                        <Input value={site.title} onChange={(e) => set({ title: e.target.value })} placeholder="My Awesome Site" autoFocus />
                    </Field>
                    <Field label="Tagline / description" hint="In a few words, explain what this site is about." className="sm:col-span-2" error={errors.description}>
                        <Input value={site.description} onChange={(e) => set({ description: e.target.value })} placeholder="Just another BoholwebCMS site" />
                    </Field>
                    <Field label="Site address (URL)" hint="Where visitors will reach your site." error={errors.url}>
                        <Input value={site.url} onChange={(e) => set({ url: e.target.value })} className="font-mono text-xs" />
                    </Field>
                    <Field label="Timezone" error={errors.timezone}>
                        <Select value={site.timezone} onChange={(e) => set({ timezone: e.target.value })} options={timezones.map((t) => ({ value: t, label: t.replaceAll('_', ' ') }))} />
                    </Field>
                </div>
            </Section>

            <Section title="Administrator account" icon={Lock}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Username" hint="Letters, numbers, dashes and underscores." error={errors.admin_username}>
                        <Input value={site.admin_username} onChange={(e) => set({ admin_username: e.target.value })} autoComplete="off" />
                    </Field>
                    <Field label="Your email" hint="Double-check it before continuing." error={errors.admin_email}>
                        <Input type="email" value={site.admin_email} onChange={(e) => set({ admin_email: e.target.value })} placeholder="you@example.com" />
                    </Field>
                    <Field label="Password" error={errors.admin_password} className="sm:col-span-2">
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Input type={showPassword ? 'text' : 'password'} value={site.admin_password} onChange={(e) => set({ admin_password: e.target.value })} className="pr-10 font-mono" autoComplete="new-password" />
                                <button type="button" onClick={() => setShowPassword((s) => !s)} className="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" aria-label="Show password">
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>
                            <Button variant="secondary" icon={RefreshCw} onClick={() => set({ admin_password: generatePassword() })}>
                                Generate
                            </Button>
                        </div>
                        <div className="mt-2 flex items-center gap-3">
                            <div className="flex h-1.5 flex-1 gap-1">
                                {[0, 1, 2, 3].map((i) => (
                                    <span key={i} className={cx('flex-1 rounded-full', i < strength.score ? strength.color : 'bg-slate-200')} />
                                ))}
                            </div>
                            <span className="w-20 text-right text-xs font-medium text-slate-600">{strength.label}</span>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">You will need this password to log in. Please store it in a secure location.</p>
                    </Field>
                </div>
            </Section>

            {themes.length > 0 && (
                <Section title="Starting theme">
                    <div className="grid gap-3 sm:grid-cols-3">
                        {themes.map((t) => (
                            <button
                                key={t.slug}
                                type="button"
                                onClick={() => set({ theme: t.slug })}
                                className={cx('overflow-hidden rounded-xl border-2 text-left transition', site.theme === t.slug ? 'border-brand-600 ring-4 ring-brand-500/15' : 'border-slate-200 hover:border-slate-300')}
                            >
                                <span className="block aspect-[4/3] bg-slate-100">{t.screenshot && <img src={t.screenshot} alt="" className="size-full object-cover object-top" />}</span>
                                <span className="flex items-center justify-between px-3 py-2 text-sm font-medium text-slate-800">
                                    {t.name}
                                    {site.theme === t.slug && <CheckCircle2 className="size-4 text-brand-600" />}
                                </span>
                            </button>
                        ))}
                    </div>
                </Section>
            )}

            <Section title="Options">
                <div className="grid gap-3">
                    <Checkbox label="Add sample content" description="A “Hello world!” post, a sample page and a comment to show how things work." checked={site.sample_content} onChange={(e) => set({ sample_content: e.target.checked })} />
                    {demoAvailable && (
                        <Checkbox label="Install the Simple Shop demo" description="Activates the example e-commerce plugin with products, cart and checkout pages." checked={site.demo} onChange={(e) => set({ demo: e.target.checked })} />
                    )}
                    <Checkbox label="Discourage search engines from indexing this site" description="Useful while the site is still being built. It is up to search engines to honor this request." checked={site.discourage_search_engines} onChange={(e) => set({ discourage_search_engines: e.target.checked })} />
                    <Checkbox label="Production mode" description="Hides detailed error messages from visitors (recommended on live servers)." checked={site.production} onChange={(e) => set({ production: e.target.checked })} />
                </div>
            </Section>
        </StepCard>
    );
}

/* =====================================================================
 | Step 4 — install & success
 * =================================================================== */

function InstallStep({ install, site, onRetry, onBack }) {
    if (install.done) {
        return (
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="bg-gradient-to-br from-brand-600 via-brand-700 to-fuchsia-700 px-8 py-10 text-white">
                    <PartyPopper className="size-10 text-brand-100" />
                    <h2 className="mt-4 text-3xl font-semibold">Success!</h2>
                    <p className="mt-1 text-brand-100">{site.title || 'Your site'} is installed. Thank you, and enjoy!</p>
                </div>
                <div className="px-8 py-6">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div className="rounded-lg bg-slate-50 px-4 py-3">
                            <dt className="text-xs text-slate-500">Username</dt>
                            <dd className="font-mono font-semibold text-slate-900">{install.done.username}</dd>
                        </div>
                        <div className="rounded-lg bg-slate-50 px-4 py-3">
                            <dt className="text-xs text-slate-500">Password</dt>
                            <dd className="text-slate-700">The password you chose.</dd>
                        </div>
                    </dl>
                    <InstallLog log={install.log} className="mt-6" />
                    <div className="mt-6 flex flex-wrap gap-2">
                        <Button href={install.done.login_url} external size="lg">
                            Log In <ArrowRight className="size-4" />
                        </Button>
                        <Button href={install.done.site_url} external variant="secondary" size="lg">
                            View your site
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <StepCard
            eyebrow="Step 4 of 4"
            title={install.error ? 'Installation failed' : 'Installing…'}
            description={install.error ? null : 'This only takes a moment. Please don’t close this page.'}
            footer={
                install.error ? (
                    <>
                        <Button variant="ghost" onClick={onBack}>
                            <ArrowLeft className="size-4" /> Back
                        </Button>
                        <Button onClick={onRetry} icon={RefreshCw}>
                            Try again
                        </Button>
                    </>
                ) : null
            }
        >
            {install.running && !install.log.length && (
                <div className="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
                    <Loader2 className="size-5 animate-spin text-brand-600" /> Creating tables, roles, your account and default content…
                </div>
            )}
            <InstallLog log={install.log} />
            {install.error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{install.error}</p>}
        </StepCard>
    );
}

function InstallLog({ log, className }) {
    if (!log.length) return null;
    return (
        <ul className={cx('space-y-2', className)}>
            {log.map((entry, i) => (
                <li key={i} className="flex items-start gap-3 text-sm">
                    {entry.ok ? <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" /> : <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />}
                    <span>
                        <span className="text-slate-800">{entry.step}</span>
                        {entry.detail && <span className="block text-xs text-amber-700">{entry.detail}</span>}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/* ------------------------------------------------------------------ */

function StepCard({ eyebrow, title, description, children, footer }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="px-6 pb-2 pt-7 sm:px-8">
                <p className="text-xs font-semibold uppercase tracking-wider text-brand-600">{eyebrow}</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{title}</h2>
                {description && <p className="mt-1.5 text-sm text-slate-600">{description}</p>}
            </div>
            <div className="px-6 py-6 sm:px-8">{children}</div>
            {footer && <div className="flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">{footer}</div>}
        </div>
    );
}

function Section({ title, icon: Icon, children }) {
    return (
        <section className="border-t border-slate-100 py-6 first:border-0 first:pt-0 last:pb-0">
            <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-800">
                {Icon && <Icon className="size-4 text-slate-400" />}
                {title}
            </h3>
            {children}
        </section>
    );
}

function Field({ label, hint, error, children, className }) {
    return (
        <label className={cx('block', className)}>
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
            <FieldError message={error} />
        </label>
    );
}

/* ------------------------------------------------------------------ helpers */

function flattenErrors(errors = {}, prefix) {
    return Object.fromEntries(
        Object.entries(errors)
            .filter(([k]) => k.startsWith(prefix))
            .map(([k, v]) => [k.slice(prefix.length), Array.isArray(v) ? v[0] : v]),
    );
}

async function reveal(log, update) {
    for (let i = 1; i <= log.length; i++) {
        update(log.slice(0, i));
        await new Promise((r) => setTimeout(r, 180));
    }
}

function generatePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^&*';
    const bytes = crypto.getRandomValues(new Uint32Array(18));
    return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

function passwordStrength(p) {
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p) && /\d/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p) && p.length >= 10) score++;
    return [
        { score: 0, label: 'Too short', color: 'bg-red-500' },
        { score: 1, label: 'Weak', color: 'bg-red-500' },
        { score: 2, label: 'Medium', color: 'bg-amber-500' },
        { score: 3, label: 'Strong', color: 'bg-emerald-500' },
        { score: 4, label: 'Very strong', color: 'bg-emerald-600' },
    ][score];
}

function guessTimezone(list, fallback) {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (list.includes(tz)) return tz;
    } catch {}
    return fallback;
}
