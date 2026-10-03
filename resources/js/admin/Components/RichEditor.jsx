import { useEffect, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import Placeholder from '@tiptap/extension-placeholder';
import {
    AlignCenter, AlignLeft, AlignRight, Bold, Brackets, Code, Code2, Heading2, ImagePlus, Italic, Link2, List, ListOrdered, Minus, Quote, Redo2, Strikethrough,
    Underline as UnderlineIcon, Undo2, Unlink,
} from 'lucide-react';
import { cx, Dropdown, DropdownItem } from './ui';
import MediaPicker from './MediaPicker';
import { tabIndent } from './FieldRenderer';

/**
 * Visual (TipTap) + Code (raw HTML) editor. Shortcodes are typed as plain
 * text, e.g. [gallery ids="1,2,3"], and rendered on the front end.
 */
export default function RichEditor({ value, onChange, initialMode = 'visual', shortcodes = [], placeholder = 'Start writing or type [ to insert a shortcode…' }) {
    // Complex HTML (divs, classes, scripts) is safest in Code mode.
    const looksComplex = /<(div|section|script|style|iframe|form|table|span[^>]+style)/i.test(value || '');
    const [mode, setMode] = useState(looksComplex ? 'html' : initialMode);
    const [mediaOpen, setMediaOpen] = useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
            Image.configure({ inline: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
        ],
        content: value || '',
        immediatelyRender: true,
        shouldRerenderOnTransaction: true,
        onUpdate: ({ editor }) => onChange(editor.isEmpty ? '' : editor.getHTML()),
    });

    // Sync external value when switching back to visual.
    useEffect(() => {
        if (editor && mode === 'visual' && editor.getHTML() !== value) {
            editor.commands.setContent(value || '', { emitUpdate: false });
        }
    }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps

    const switchTo = (next) => {
        if (next === 'visual' && looksComplexNow(value) && !window.confirm('This content contains HTML the visual editor may simplify (e.g. div/class/style attributes). Switch anyway?')) {
            return;
        }
        setMode(next);
    };

    const insertText = (text) => {
        if (mode === 'html') {
            onChange((value || '') + text);
        } else {
            editor?.chain().focus().insertContent(text).run();
        }
    };

    return (
        <div className="pm-editor rounded-lg border border-slate-300 bg-white shadow-sm">
            <div className="sticky top-12 z-10 flex flex-wrap items-center gap-0.5 rounded-t-lg border-b border-slate-200 bg-slate-50 px-2 py-1.5">
                {mode === 'visual' && editor ? (
                    <>
                        <select
                            className="mr-1 rounded border border-slate-300 bg-white px-1.5 py-1 text-xs"
                            value={editor.isActive('heading', { level: 1 }) ? 'h1' : editor.isActive('heading', { level: 2 }) ? 'h2' : editor.isActive('heading', { level: 3 }) ? 'h3' : editor.isActive('heading', { level: 4 }) ? 'h4' : 'p'}
                            onChange={(e) => {
                                const v = e.target.value;
                                if (v === 'p') editor.chain().focus().setParagraph().run();
                                else editor.chain().focus().toggleHeading({ level: Number(v[1]) }).run();
                            }}
                        >
                            <option value="p">Paragraph</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="h4">Heading 4</option>
                        </select>
                        <Tool icon={Bold} label="Bold" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()} />
                        <Tool icon={Italic} label="Italic" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()} />
                        <Tool icon={UnderlineIcon} label="Underline" active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()} />
                        <Tool icon={Strikethrough} label="Strikethrough" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()} />
                        <Sep />
                        <Tool icon={List} label="Bulleted list" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()} />
                        <Tool icon={ListOrdered} label="Numbered list" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()} />
                        <Tool icon={Quote} label="Quote" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()} />
                        <Tool icon={Code2} label="Code block" active={editor.isActive('codeBlock')} onClick={() => editor.chain().focus().toggleCodeBlock().run()} />
                        <Sep />
                        <Tool icon={AlignLeft} label="Align left" active={editor.isActive({ textAlign: 'left' })} onClick={() => editor.chain().focus().setTextAlign('left').run()} />
                        <Tool icon={AlignCenter} label="Align center" active={editor.isActive({ textAlign: 'center' })} onClick={() => editor.chain().focus().setTextAlign('center').run()} />
                        <Tool icon={AlignRight} label="Align right" active={editor.isActive({ textAlign: 'right' })} onClick={() => editor.chain().focus().setTextAlign('right').run()} />
                        <Sep />
                        <Tool
                            icon={Link2}
                            label="Insert link"
                            active={editor.isActive('link')}
                            onClick={() => {
                                const prev = editor.getAttributes('link').href || '';
                                const url = window.prompt('Link URL', prev || 'https://');
                                if (url === null) return;
                                if (url === '') editor.chain().focus().unsetLink().run();
                                else editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                            }}
                        />
                        <Tool icon={Unlink} label="Remove link" disabled={!editor.isActive('link')} onClick={() => editor.chain().focus().unsetLink().run()} />
                        <Tool icon={ImagePlus} label="Add media" onClick={() => setMediaOpen(true)} />
                        <Tool icon={Minus} label="Horizontal line" onClick={() => editor.chain().focus().setHorizontalRule().run()} />
                        <Sep />
                        <Tool icon={Undo2} label="Undo" disabled={!editor.can().undo()} onClick={() => editor.chain().focus().undo().run()} />
                        <Tool icon={Redo2} label="Redo" disabled={!editor.can().redo()} onClick={() => editor.chain().focus().redo().run()} />
                    </>
                ) : (
                    <>
                        <Tool icon={ImagePlus} label="Add media" onClick={() => setMediaOpen(true)} />
                        <span className="px-2 text-xs text-slate-500">Editing raw HTML</span>
                    </>
                )}
                {shortcodes.length > 0 && (
                    <Dropdown
                        align="left"
                        trigger={
                            <button type="button" className="ml-1 flex items-center gap-1 rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200" title="Insert shortcode">
                                <Brackets className="size-4" /> Shortcode
                            </button>
                        }
                    >
                        <div className="max-h-72 overflow-y-auto">
                            {shortcodes.map((tag) => (
                                <DropdownItem key={tag} onClick={() => insertText(`[${tag}]`)}>
                                    <code className="text-xs">[{tag}]</code>
                                </DropdownItem>
                            ))}
                        </div>
                    </Dropdown>
                )}
                <div className="ml-auto flex overflow-hidden rounded border border-slate-300 text-xs">
                    <button type="button" className={cx('px-2.5 py-1', mode === 'visual' ? 'bg-white font-semibold text-slate-900' : 'bg-slate-100 text-slate-500')} onClick={() => switchTo('visual')}>
                        Visual
                    </button>
                    <button type="button" className={cx('flex items-center gap-1 px-2.5 py-1', mode === 'html' ? 'bg-white font-semibold text-slate-900' : 'bg-slate-100 text-slate-500')} onClick={() => switchTo('html')}>
                        <Code className="size-3.5" /> Code
                    </button>
                </div>
            </div>

            {mode === 'visual' ? (
                <EditorContent editor={editor} />
            ) : (
                <textarea
                    className="block min-h-[480px] w-full resize-y rounded-b-lg border-0 p-4 font-mono text-[13px] leading-relaxed text-slate-800 focus:outline-none focus:ring-0"
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={tabIndent}
                    spellCheck={false}
                />
            )}

            <MediaPicker
                open={mediaOpen}
                onClose={() => setMediaOpen(false)}
                multiple
                title="Add media"
                onSelect={(items) => {
                    setMediaOpen(false);
                    const list = Array.isArray(items) ? items : [items];
                    if (list.length > 1 && list.every((m) => m.is_image)) {
                        insertText(`[gallery ids="${list.map((m) => m.id).join(',')}"]`);
                        return;
                    }
                    for (const m of list) {
                        if (mode === 'visual' && m.is_image) {
                            editor?.chain().focus().setImage({ src: m.sizes?.large || m.url, alt: m.alt || m.title || '' }).run();
                        } else if (m.is_image) {
                            insertText(`<img src="${m.sizes?.large || m.url}" alt="${(m.alt || '').replace(/"/g, '&quot;')}">`);
                        } else {
                            insertText(mode === 'visual' ? `<a href="${m.url}">${m.title || m.filename}</a>` : `<a href="${m.url}">${m.title || m.filename}</a>`);
                        }
                    }
                }}
            />
        </div>
    );
}

function looksComplexNow(html) {
    return /<(div|section|script|style|iframe|form|table)|\sclass=|\sstyle=/i.test(html || '');
}

function Tool({ icon: Icon, label, active, onClick, disabled }) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className={cx('rounded p-1.5 text-slate-600 hover:bg-slate-200 disabled:opacity-30', active && 'bg-slate-200 text-slate-900')}
        >
            <Icon className="size-4" />
        </button>
    );
}

function Sep() {
    return <span className="mx-1 h-5 w-px bg-slate-300" />;
}
