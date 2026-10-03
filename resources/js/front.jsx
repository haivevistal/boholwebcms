import '../css/front.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { createRuntime } from './shared/runtime';
import { waitFor } from './shared/registry';
import * as themeApi from './front/theme-api';
import Theme from './front/Theme';

/*
 * Front-end runtime. Theme bundles (content/themes/x/dist/theme.js) and
 * plugin scripts load after this file and register themselves:
 *   CMS.registerTheme('aurora', { Layout, templates: { index, single, page, '404': NotFound } })
 */
const CMS = createRuntime('front', {
    name: document.querySelector('meta[property="og:site_name"]')?.content,
    theme: themeApi, // `import { Content, Menu } from '@boholwebcms/theme'` in themes
    components: themeApi,
});

createInertiaApp({
    title: (title) => title,
    resolve: async (name) => {
        if (name === 'Front/Theme') return Theme;
        // Full-page components from plugins: Inertia::render('plugin:shop/Cart')
        if (name.startsWith('plugin:')) return waitFor(CMS.registries.adminPages, name.slice(7));
        throw new Error(`Unknown front-end page: ${name}`);
    },
    setup({ el, App, props }) {
        CMS.hooks.doAction('front.init', props);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#6366f1', delay: 150 },
});
