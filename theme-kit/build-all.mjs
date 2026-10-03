/**
 * BoholwebCMS theme kit — builds every theme in content/themes that has a
 * src/index.(jsx|js|tsx) into dist/theme.js (+ dist/theme.css).
 *
 *   npm run build:themes            # all themes
 *   npm run build:themes -- aurora  # one theme
 *   node theme-kit/build-all.mjs aurora --watch
 *
 * React, the JSX runtime, @inertiajs/react and @boholwebcms/theme are NOT
 * bundled: they map to the globals exposed by the CMS runtime (window.CMS),
 * so a theme bundle is tiny and shares one React instance with the site.
 * The bundle is an IIFE that ends with CMS.registerTheme(<slug>, <default export>).
 */
import { build } from 'vite';
import react from '@vitejs/plugin-react';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const themesDir = path.join(root, 'content', 'themes');
const args = process.argv.slice(2);
const watch = args.includes('--watch');
const only = args.filter((a) => !a.startsWith('--'));

export const externals = {
    react: 'CMS.React',
    'react-dom': 'CMS.ReactDOM',
    'react-dom/client': 'CMS.ReactDOM',
    'react/jsx-runtime': 'CMS.jsxRuntime',
    'react/jsx-dev-runtime': 'CMS.jsxRuntime',
    '@inertiajs/react': 'CMS.Inertia',
    '@boholwebcms/theme': 'CMS.theme',
};

export async function buildTheme(slug, { watch: w = false } = {}) {
    const dir = path.join(themesDir, slug);
    const entry = ['src/index.jsx', 'src/index.js', 'src/index.tsx'].map((f) => path.join(dir, f)).find((f) => fs.existsSync(f));
    if (!entry) return false;

    console.log(`\n▸ Building theme "${slug}"`);
    await build({
        configFile: false,
        root: dir,
        logLevel: 'warn',
        plugins: [react({ jsxRuntime: 'automatic' })],
        define: { 'process.env.NODE_ENV': JSON.stringify('production') },
        build: {
            outDir: path.join(dir, 'dist'),
            emptyOutDir: true,
            sourcemap: false,
            cssCodeSplit: false,
            minify: true,
            watch: w ? {} : null,
            lib: {
                entry,
                formats: ['iife'],
                name: '__pmTheme',
                fileName: () => 'theme.js',
                cssFileName: 'theme',
            },
            rollupOptions: {
                external: Object.keys(externals),
                output: {
                    globals: externals,
                    exports: 'auto',
                    footer: `(window.CMS ? window.CMS.registerTheme(${JSON.stringify(slug)}, __pmTheme) : (window.__cmsPendingThemes = window.__cmsPendingThemes || []).push([${JSON.stringify(slug)}, __pmTheme]));`,
                },
            },
        },
    });
    console.log(`  ✓ content/themes/${slug}/dist/theme.js`);
    return true;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1])) {
    const slugs = fs.readdirSync(themesDir).filter((s) => fs.statSync(path.join(themesDir, s)).isDirectory() && (!only.length || only.includes(s)));
    let built = 0;
    for (const slug of slugs) {
        if (await buildTheme(slug, { watch })) built++;
    }
    console.log(`\nBuilt ${built} theme(s).`);
}
