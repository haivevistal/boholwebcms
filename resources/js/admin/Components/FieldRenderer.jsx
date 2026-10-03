import { useState } from 'react';
import { ImageIcon, X } from 'lucide-react';
import { Button, Checkbox, cx, Input, Select, Textarea, Toggle } from './ui';
import MediaPicker from './MediaPicker';
import HtmlContent from '../../shared/HtmlContent';
import { useRegistry } from '../../shared/registry';

/**
 * Renders one declarative field (settings pages, meta boxes, customizer,
 * user profile fields). Unknown types fall back to custom components
 * registered with CMS.registerField(type, Component).
 */
export default function FieldRenderer({ field, value, onChange, context = {}, error }) {
    const custom = useRegistry(window.CMS.registries.fields);
    const id = `field-${field.name || field.id}`;
    const attrs = field.attributes || {};
    const width = { small: 'max-w-32', regular: 'max-w-md', large: 'max-w-2xl' }[field.width] || 'max-w-md';
    const choices = normalizeChoices(field.choices);

    if (custom[field.type]) {
        const Custom = custom[field.type];
        return <Custom field={field} value={value} onChange={onChange} context={context} error={error} />;
    }

    switch (field.type) {
        case 'textarea':
            return <Textarea id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} placeholder={field.placeholder || ''} rows={attrs.rows || 5} className="max-w-2xl" error={error} />;

        case 'code':
            return (
                <Textarea
                    id={id}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    rows={attrs.rows || 10}
                    spellCheck={false}
                    className="max-w-3xl font-mono text-xs"
                    onKeyDown={tabIndent}
                />
            );

        case 'number':
            return (
                <div className="flex items-center gap-2">
                    <Input id={id} type="number" value={value ?? ''} onChange={(e) => onChange(e.target.value)} min={attrs.min} max={attrs.max} step={attrs.step} className={field.width === 'small' ? 'w-28' : width} error={error} />
                    {attrs.suffix && <span className="text-sm text-slate-500">{attrs.suffix}</span>}
                </div>
            );

        case 'range':
            return (
                <div className="flex max-w-md items-center gap-3">
                    <input type="range" className="flex-1 accent-brand-600" value={value ?? 0} min={attrs.min ?? 0} max={attrs.max ?? 100} step={attrs.step ?? 1} onChange={(e) => onChange(Number(e.target.value))} />
                    <span className="w-12 text-right text-sm tabular-nums text-slate-600">{value}</span>
                </div>
            );

        case 'checkbox':
            return <Checkbox id={id} checked={!!value && value !== '0'} onChange={(e) => onChange(e.target.checked)} label={field.description_inline ?? field.description} />;

        case 'toggle':
            return <Toggle checked={!!value && value !== '0'} onChange={onChange} label={field.description} />;

        case 'checkboxes': {
            const current = Array.isArray(value) ? value : [];
            return (
                <div className="flex flex-col gap-1.5">
                    {choices.map((c) => (
                        <Checkbox
                            key={c.value}
                            label={c.label}
                            checked={current.includes(c.value)}
                            onChange={(e) => onChange(e.target.checked ? [...current, c.value] : current.filter((v) => v !== c.value))}
                        />
                    ))}
                </div>
            );
        }

        case 'radio':
            return <RadioField field={field} id={id} value={value} onChange={onChange} choices={choices} />;

        case 'select':
        case 'page':
        case 'category':
        case 'timezone':
            return <Select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} options={choices} className={width} error={error} />;

        case 'multiselect':
            return (
                <Select id={id} multiple value={Array.isArray(value) ? value : []} onChange={(e) => onChange([...e.target.selectedOptions].map((o) => o.value))} options={choices} className={cx(width, 'min-h-32')} />
            );

        case 'role':
            return <Select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} options={context.roles || {}} className={width} />;

        case 'color':
            return (
                <div className="flex items-center gap-2">
                    <input type="color" value={value || '#000000'} onChange={(e) => onChange(e.target.value)} className="h-9 w-12 cursor-pointer rounded border border-slate-300 bg-white p-0.5" />
                    <Input value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="w-32 font-mono" placeholder="#000000" />
                    {value && (
                        <Button variant="ghost" size="sm" onClick={() => onChange(field.default ?? '')}>
                            Default
                        </Button>
                    )}
                </div>
            );

        case 'media':
            return <MediaField value={value} onChange={onChange} preview={context.mediaPreviews?.[value]} onPick={context.onMediaSelect} />;

        case 'permalink':
            return <PermalinkField value={value ?? ''} onChange={onChange} choices={field.choices} />;

        case 'html':
            return <HtmlContent html={field.html || ''} className="pm-html text-sm" />;

        default:
            return (
                <Input
                    id={id}
                    type={['email', 'url', 'password', 'date', 'datetime-local', 'time', 'tel'].includes(field.type) ? field.type : 'text'}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.placeholder || ''}
                    className={width}
                    error={error}
                />
            );
    }
}

export function normalizeChoices(choices) {
    if (!choices) return [];
    if (Array.isArray(choices)) {
        return choices.map((c) => (typeof c === 'object' ? { value: String(c.value), label: c.label } : { value: String(c), label: String(c) }));
    }
    return Object.entries(choices).map(([value, label]) => ({ value: String(value), label: String(label) }));
}

function RadioField({ field, id, value, onChange, choices }) {
    const allowCustom = field.attributes?.allow_custom;
    const isCustom = allowCustom && value !== undefined && value !== null && !choices.some((c) => c.value === String(value));
    return (
        <div className="flex flex-col gap-2">
            {choices.map((c) => (
                <label key={c.value} className="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name={id} className="size-4 border-slate-300 text-brand-600 focus:ring-brand-500" checked={String(value) === c.value} onChange={() => onChange(c.value)} />
                    {c.label}
                </label>
            ))}
            {allowCustom && (
                <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name={id} className="size-4 border-slate-300 text-brand-600" checked={isCustom} onChange={() => onChange(value || '')} />
                    Custom:
                    <Input className="w-40 font-mono" value={isCustom ? value : ''} onChange={(e) => onChange(e.target.value)} />
                </label>
            )}
        </div>
    );
}

function MediaField({ value, onChange, preview, onPick }) {
    const [open, setOpen] = useState(false);
    const [thumb, setThumb] = useState(preview);
    return (
        <div className="flex items-center gap-3">
            <div className="grid size-20 place-items-center overflow-hidden rounded-md border border-dashed border-slate-300 bg-slate-50">
                {value && thumb ? <img src={thumb} alt="" className="size-full object-cover" /> : <ImageIcon className="size-6 text-slate-300" />}
            </div>
            <div className="flex gap-2">
                <Button variant="secondary" size="sm" onClick={() => setOpen(true)}>
                    {value ? 'Change' : 'Select image'}
                </Button>
                {value ? (
                    <Button variant="danger-ghost" size="sm" onClick={() => onChange(null)} icon={X}>
                        Remove
                    </Button>
                ) : null}
            </div>
            <MediaPicker
                open={open}
                onClose={() => setOpen(false)}
                type="image"
                onSelect={(m) => {
                    onPick?.(m);
                    onChange(m.id);
                    setThumb(m.thumb || m.url);
                    setOpen(false);
                }}
            />
        </div>
    );
}

function PermalinkField({ value, onChange, choices }) {
    const base = window.location.origin;
    const samples = {
        plain: `${base}/?p=123`,
        day: `${base}/2026/10/03/sample-post`,
        month: `${base}/2026/10/sample-post`,
        numeric: `${base}/archives/123`,
        postname: `${base}/sample-post`,
    };
    const known = Object.entries(choices || {});
    const matched = known.find(([, structure]) => structure === value);

    return (
        <div className="flex flex-col gap-2.5">
            {known.map(([key, structure]) => (
                <label key={key} className="grid grid-cols-[auto_140px_1fr] items-center gap-3 text-sm">
                    <input type="radio" name="permalink" className="size-4 text-brand-600" checked={matched?.[0] === key} onChange={() => onChange(structure)} />
                    <span className="font-medium capitalize text-slate-700">{key === 'postname' ? 'Post name' : key === 'day' ? 'Day and name' : key === 'month' ? 'Month and name' : key}</span>
                    <code className="truncate rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">{samples[key]}</code>
                </label>
            ))}
            <label className="grid grid-cols-[auto_140px_1fr] items-center gap-3 text-sm">
                <input type="radio" name="permalink" className="size-4 text-brand-600" checked={!matched} onChange={() => onChange('/%category%/%postname%')} />
                <span className="font-medium text-slate-700">Custom Structure</span>
                <span className="flex items-center gap-1">
                    <code className="text-xs text-slate-500">{base}</code>
                    <Input value={!matched ? value : ''} onChange={(e) => onChange(e.target.value)} className="font-mono text-xs" placeholder="/%year%/%postname%" />
                </span>
            </label>
            <p className="text-xs text-slate-500">
                Available tags:{' '}
                {['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%postname%', '%category%', '%author%'].map((t) => (
                    <button key={t} type="button" className="mr-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] hover:bg-slate-200" onClick={() => onChange((matched ? '' : value) + '/' + t)}>
                        {t}
                    </button>
                ))}
            </p>
        </div>
    );
}

export function tabIndent(e) {
    if (e.key !== 'Tab') return;
    e.preventDefault();
    const el = e.target;
    const { selectionStart: s, selectionEnd: end, value } = el;
    // Use the native setter so React's onChange fires.
    const setter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set;
    setter.call(el, value.slice(0, s) + '    ' + value.slice(end));
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.selectionStart = el.selectionEnd = s + 4;
}
