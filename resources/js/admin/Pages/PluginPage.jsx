import AdminLayout from '../Layouts/AdminLayout';
import { Notice, PageHeader } from '../Components/ui';
import HtmlContent from '../../shared/HtmlContent';
import { useRegistry } from '../../shared/registry';

/**
 * Admin pages registered by plugins/themes (add_menu_page / add_submenu_page).
 * The callback returned either HTML or a React component name.
 */
export default function PluginPage({ page, slug }) {
    const components = useRegistry(window.CMS.registries.adminComponents);

    if (page.type === 'component') {
        const Component = components[page.component];
        return (
            <AdminLayout title={page.title}>
                {Component ? (
                    <Component {...page.props} slug={slug} />
                ) : (
                    <>
                        <PageHeader title={page.title} />
                        <Notice type="warning">
                            Waiting for component <code>{page.component}</code>… It must be registered with <code>CMS.registerAdminComponent()</code> from a script enqueued on <code>admin_enqueue_scripts</code>.
                        </Notice>
                    </>
                )}
            </AdminLayout>
        );
    }

    return (
        <AdminLayout title={page.title}>
            <PageHeader title={page.title} />
            <HtmlContent html={page.html} className="pm-html" />
        </AdminLayout>
    );
}
