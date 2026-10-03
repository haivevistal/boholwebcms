import '../css/admin.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { createRuntime, } from './shared/runtime';
import { waitFor } from './shared/registry';
import * as UI from './admin/Components/ui';
import Icon, { ICONS } from './admin/Components/Icon';
import AdminLayout from './admin/Layouts/AdminLayout';
import HtmlContent from './shared/HtmlContent';
import FieldRenderer from './admin/Components/FieldRenderer';
import MediaPicker from './admin/Components/MediaPicker';

/*
 * window.CMS in the admin also exposes the admin UI kit so plugin screens
 * look native without bundling anything:
 *
 *   const { React, components: { AdminLayout, Card, Button } } = CMS;
 *   CMS.registerAdminComponent('my-plugin/Report', (props) => React.createElement(Card, { title: 'Report' }, '…'));
 */
const CMS = createRuntime('admin', {
    components: { ...UI, Icon, AdminLayout, HtmlContent, FieldRenderer, MediaPicker },
    icons: { ...ICONS },
    registerIcon: (name, Component) => {
        CMS.icons[name] = Component;
    },
});

const pages = import.meta.glob('./admin/Pages/**/*.jsx');

createInertiaApp({
    title: (title) => title,
    resolve: async (name) => {
        // Full pages provided by plugins: Inertia::render('plugin:vendor/Page')
        if (name.startsWith('plugin:')) {
            const key = name.slice(7);
            return waitFor(CMS.registries.adminPages, key).catch(() => {
                return () => (
                    <AdminLayout title="Missing component">
                        <UI.Notice type="error">
                            The admin page component <code>{key}</code> was not registered. Make sure the plugin enqueues its admin script and calls{' '}
                            <code>CMS.registerAdminPage('{key}', Component)</code>.
                        </UI.Notice>
                    </AdminLayout>
                );
            });
        }

        const loader = pages[`./admin/Pages/${name}.jsx`];
        if (!loader) throw new Error(`Admin page not found: ${name}`);
        const module = await loader();
        return module.default;
    },
    setup({ el, App, props }) {
        CMS.hooks.doAction('admin.init', props);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#6366f1' },
});
