import * as React from 'react';
import * as ReactDOMClient from 'react-dom/client';
import * as jsxRuntime from 'react/jsx-runtime';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { createHooks } from './hooks';
import { createRegistry, useRegistry, waitFor } from './registry';

/**
 * window.CMS — the public JavaScript API shared by the admin and the front end.
 *
 * Theme bundles and plugin scripts don't bundle React; they use the copies
 * exposed here (the theme-kit maps `react`, `react/jsx-runtime`,
 * `@inertiajs/react` and `@boholwebcms/theme` to these globals).
 */
export function createRuntime(context, extra = {}) {
    if (window.CMS?.__ready) return window.CMS;

    const themes = createRegistry();
    const templates = createRegistry();
    const adminComponents = createRegistry();
    const adminPages = createRegistry();
    const fields = createRegistry();
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const CMS = {
        __ready: true,
        context, // "admin" | "front"
        React,
        ReactDOM: ReactDOMClient,
        jsxRuntime,
        Inertia: { Head, Link, router, useForm, usePage },
        axios,
        hooks: createHooks(),

        // --- registries -------------------------------------------------
        registries: { themes, templates, adminComponents, adminPages, fields },
        useRegistry,
        waitFor,

        /** Themes: CMS.registerTheme('aurora', { templates: { index, single, page, ... }, Layout }) */
        registerTheme: (slug, definition) => themes.set(slug, definition?.default ?? definition),
        /** Plugins can ship front-end templates, used when the theme lacks them: CMS.registerTemplate('single-product', Comp) */
        registerTemplate: (name, Component) => templates.set(name, Component),
        /** Admin page bodies returned as ['component' => 'vendor/Name'] or dashboard widgets */
        registerAdminComponent: (name, Component) => adminComponents.set(name, Component),
        /** Full Inertia pages rendered by plugin controllers: Inertia::render('plugin:vendor/Page') */
        registerAdminPage: (name, Component) => adminPages.set(name, Component),
        /** Custom field types for settings pages, meta boxes and the customizer */
        registerField: (type, Component) => fields.set(type, Component),

        // --- AJAX helpers -------------------------------------------------
        /** POST /cms-ajax — fires ajax_{action} / ajax_nopriv_{action} */
        ajax: (action, data = {}) =>
            axios.post('/cms-ajax', { action, ...data }, { headers: { 'X-CSRF-TOKEN': csrf() } }).then((r) => r.data),
        /** POST /admin/ajax — fires admin_ajax_{action} */
        adminAjax: (action, data = {}) =>
            axios.post('/admin/ajax', { action, ...data }, { headers: { 'X-CSRF-TOKEN': csrf() } }).then((r) => r.data),

        components: {},
        ...extra,
    };

    // Flush registrations queued before the runtime existed.
    for (const [slug, def] of window.__cmsPendingThemes || []) CMS.registerTheme(slug, def);

    window.CMS = CMS;
    window.React ??= React;
    return CMS;
}
