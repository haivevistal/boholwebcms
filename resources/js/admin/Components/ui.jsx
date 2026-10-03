import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, Loader2, X } from 'lucide-react';

export const cx = (...classes) => classes.filter(Boolean).join(' ');

/* ------------------------------------------------------------------ Buttons */

const buttonVariants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 border-brand-600 shadow-sm',
    secondary: 'bg-white text-slate-700 hover:bg-slate-50 border-slate-300 shadow-sm',
    ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 border-transparent',
    danger: 'bg-red-600 text-white hover:bg-red-700 border-red-600 shadow-sm',
    'danger-ghost': 'bg-transparent text-red-600 hover:bg-red-50 border-transparent',
    link: 'bg-transparent text-brand-600 hover:underline border-transparent px-0',
};
const buttonSizes = { xs: 'px-2 py-1 text-xs', sm: 'px-2.5 py-1.5 text-xs', md: 'px-3.5 py-2 text-sm', lg: 'px-5 py-2.5 text-sm' };

export function Button({ variant = 'primary', size = 'md', href, loading, className, children, icon: Icon, external, preserveScroll, preserveState, method, as, data, ...props }) {
    const classes = cx(
        'inline-flex items-center justify-center gap-1.5 rounded-md border font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap',
        buttonVariants[variant],
        buttonSizes[size],
        className,
    );
    const content = (
        <>
            {loading ? <Loader2 className="size-4 animate-spin" /> : Icon ? <Icon className="size-4" /> : null}
            {children}
        </>
    );
    if (href) {
        return external ? (
            <a href={href} className={classes} {...props}>
                {content}
            </a>
        ) : (
            <Link href={href} className={classes} preserveScroll={preserveScroll} preserveState={preserveState} method={method} as={as} data={data} {...props}>
                {content}
            </Link>
        );
    }
    return (
        <button type="button" className={classes} disabled={loading || props.disabled} {...props}>
            {content}
        </button>
    );
}

/* ------------------------------------------------------------------ Layout */

export function Card({ title, actions, children, className, bodyClassName, footer }) {
    return (
        <section className={cx('rounded-lg border border-slate-200 bg-white shadow-sm', className)}>
            {(title || actions) && (
                <header className="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    {title && <h2 className="text-sm font-semibold text-slate-800">{title}</h2>}
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={cx('p-4', bodyClassName)}>{children}</div>
            {footer && <footer className="border-t border-slate-200 bg-slate-50 px-4 py-3 rounded-b-lg">{footer}</footer>}
        </section>
    );
}

export function PageHeader({ title, actions, description, children }) {
    return (
        <div className="mb-6">
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
            {description && <p className="mt-1.5 max-w-3xl text-sm text-slate-600">{description}</p>}
            {children}
        </div>
    );
}

export function EmptyState({ icon: Icon, title, children }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            {Icon && <Icon className="mb-3 size-10 text-slate-300" />}
            <p className="font-medium text-slate-700">{title}</p>
            {children && <div className="mt-1 text-sm text-slate-500">{children}</div>}
        </div>
    );
}

export function Badge({ color = 'slate', children, className }) {
    const colors = {
        slate: 'bg-slate-100 text-slate-700',
        green: 'bg-emerald-100 text-emerald-800',
        amber: 'bg-amber-100 text-amber-800',
        red: 'bg-red-100 text-red-700',
        blue: 'bg-sky-100 text-sky-800',
        brand: 'bg-brand-100 text-brand-700',
    };
    return <span className={cx('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', colors[color], className)}>{children}</span>;
}

export function Notice({ type = 'info', children, onDismiss, className }) {
    const styles = {
        info: 'border-sky-500',
        success: 'border-emerald-500',
        warning: 'border-amber-500',
        error: 'border-red-500',
    };
    return (
        <div className={cx('flex items-start gap-3 rounded-md border-l-4 bg-white px-4 py-3 text-sm shadow-sm', styles[type], className)}>
            <div className="flex-1">{children}</div>
            {onDismiss && (
                <button type="button" onClick={onDismiss} className="text-slate-400 hover:text-slate-600" aria-label="Dismiss">
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

export function Spinner({ className }) {
    return <Loader2 className={cx('size-5 animate-spin text-slate-400', className)} />;
}

/* ------------------------------------------------------------------ Forms */

// An explicit width class (w-44, w-full, ...) replaces the default full width.
const withWidth = (base, className) => (/(^|\s)w-/.test(className || '') ? base.replace('w-full', '') : base);

const inputBase =
    'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 disabled:bg-slate-50';

export function Input({ className, error, ...props }) {
    return <input className={cx(withWidth(inputBase, className), error && 'border-red-500', className)} {...props} />;
}

export function Textarea({ className, error, ...props }) {
    return <textarea className={cx(withWidth(inputBase, className), 'min-h-24', error && 'border-red-500', className)} {...props} />;
}

export function Select({ className, options, children, placeholder, error, ...props }) {
    return (
        <select className={cx(withWidth(inputBase, className), 'pr-8', error && 'border-red-500', className)} {...props}>
            {placeholder && <option value="">{placeholder}</option>}
            {options &&
                (Array.isArray(options)
                    ? options.map((o) => (
                          <option key={o.value} value={o.value}>
                              {o.label}
                          </option>
                      ))
                    : Object.entries(options).map(([value, label]) => (
                          <option key={value} value={value}>
                              {label}
                          </option>
                      )))}
            {children}
        </select>
    );
}

export function Checkbox({ label, className, description, ...props }) {
    return (
        <label className={cx('inline-flex items-start gap-2 text-sm text-slate-700', className)}>
            <input type="checkbox" className="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" {...props} />
            <span>
                {label}
                {description && <span className="block text-xs text-slate-500">{description}</span>}
            </span>
        </label>
    );
}

export function Toggle({ checked, onChange, label, disabled }) {
    return (
        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <button
                type="button"
                role="switch"
                aria-checked={!!checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={cx('relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors', checked ? 'bg-brand-600' : 'bg-slate-300')}
            >
                <span className={cx('absolute top-0.5 size-4 rounded-full bg-white shadow transition-transform', checked ? 'translate-x-4' : 'translate-x-0.5')} />
            </button>
            {label}
        </label>
    );
}

export function Label({ children, htmlFor, className }) {
    return (
        <label htmlFor={htmlFor} className={cx('mb-1 block text-sm font-medium text-slate-700', className)}>
            {children}
        </label>
    );
}

export function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs text-red-600">{message}</p> : null;
}

export function FormRow({ label, htmlFor, description, error, children }) {
    return (
        <div className="grid gap-2 border-b border-slate-100 py-4 last:border-0 md:grid-cols-[220px_1fr] md:gap-6">
            <div className="pt-2 text-sm font-medium text-slate-700">{label && <label htmlFor={htmlFor}>{label}</label>}</div>
            <div className="min-w-0">
                {children}
                {description && <p className="mt-1.5 text-xs text-slate-500" dangerouslySetInnerHTML={{ __html: description }} />}
                <FieldError message={error} />
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ Modal */

export function Modal({ open, onClose, title, children, footer, size = 'md' }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;
    const widths = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl', full: 'max-w-[95vw]' };

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-8" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
            <div className={cx('relative flex max-h-[90vh] w-full flex-col rounded-xl bg-white shadow-2xl', widths[size])}>
                <header className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 className="font-semibold text-slate-900">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                        <X className="size-5" />
                    </button>
                </header>
                <div className="flex-1 overflow-y-auto p-5">{children}</div>
                {footer && <footer className="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 rounded-b-xl">{footer}</footer>}
            </div>
        </div>,
        document.body,
    );
}

export function ConfirmButton({ message = 'Are you sure?', onConfirm, children, ...props }) {
    return (
        <Button
            {...props}
            onClick={(e) => {
                e.preventDefault();
                if (window.confirm(message)) onConfirm();
            }}
        >
            {children}
        </Button>
    );
}

/* ------------------------------------------------------------------ Lists */

export function Pagination({ meta }) {
    if (!meta || meta.last_page <= 1) return null;
    const { current_page, last_page, links, total, from, to } = meta;
    const prev = links?.find((l) => l.label.includes('Previous'))?.url ?? meta.prev_page_url;
    const next = links?.find((l) => l.label.includes('Next'))?.url ?? meta.next_page_url;

    return (
        <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span>
                {from}–{to} of {total} items
            </span>
            <div className="flex items-center gap-1">
                <Button variant="secondary" size="sm" href={prev || undefined} disabled={!prev} preserveScroll>
                    <ChevronLeft className="size-4" />
                </Button>
                <span className="px-2">
                    Page {current_page} of {last_page}
                </span>
                <Button variant="secondary" size="sm" href={next || undefined} disabled={!next} preserveScroll>
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}

export function StatusTabs({ tabs, active, buildHref }) {
    return (
        <nav className="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
            {tabs.map((tab, i) => (
                <span key={tab.key} className="flex items-center">
                    {i > 0 && <span className="mx-1 text-slate-300">|</span>}
                    <Link
                        href={buildHref(tab.key)}
                        preserveScroll
                        className={cx(active === tab.key ? 'font-semibold text-slate-900' : 'text-brand-600 hover:text-brand-700')}
                    >
                        {tab.label} <span className="text-slate-500">({tab.count})</span>
                    </Link>
                </span>
            ))}
        </nav>
    );
}

export function SearchBox({ value, onSearch, placeholder = 'Search…' }) {
    const [q, setQ] = useState(value || '');
    return (
        <form
            className="flex items-center gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                onSearch(q);
            }}
        >
            <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} className="w-56" />
            <Button type="submit" variant="secondary">
                Search
            </Button>
        </form>
    );
}

/** Small click-outside dropdown. */
export function Dropdown({ trigger, children, align = 'right' }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
        const close = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);
    return (
        <div className="relative" ref={ref}>
            <div onClick={() => setOpen((o) => !o)}>{trigger}</div>
            {open && (
                <div
                    className={cx('absolute z-40 mt-2 min-w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg', align === 'right' ? 'right-0' : 'left-0')}
                    onClick={() => setOpen(false)}
                >
                    {children}
                </div>
            )}
        </div>
    );
}

export function DropdownItem({ href, onClick, children, external, method, as }) {
    const cls = 'block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50';
    if (href && external) {
        return (
            <a href={href} className={cls}>
                {children}
            </a>
        );
    }
    if (href) {
        return (
            <Link href={href} className={cls} method={method} as={as}>
                {children}
            </Link>
        );
    }
    return (
        <button type="button" className={cls} onClick={onClick}>
            {children}
        </button>
    );
}
