import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import FieldRenderer from '../../Components/FieldRenderer';
import { Button, Card, FormRow, PageHeader } from '../../Components/ui';

/**
 * Renders any settings page registered with register_settings_page() —
 * the core General/Writing/Reading/... screens and every plugin page.
 */
export default function SettingsPage({ page, roles, mediaPreviews }) {
    const form = useForm({ values: page.values });

    const visible = (field) => {
        if (!field.depends_on) return true;
        const v = form.data.values[field.depends_on.field];
        const want = field.depends_on.value;
        return typeof want === 'boolean' ? !!v === want : String(v) === String(want);
    };

    return (
        <AdminLayout title={page.title}>
            <PageHeader title={page.title} description={page.description} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/admin/settings/${page.slug}`, { preserveScroll: true });
                }}
                className="max-w-5xl space-y-6"
            >
                {page.sections.map((section) => (
                    <Card key={section.id} title={section.title}>
                        {section.description && <p className="mb-2 text-sm text-slate-600">{section.description}</p>}
                        {section.fields.filter(visible).map((field, i) => (
                            <FormRow
                                key={field.name || i}
                                label={field.label}
                                htmlFor={`field-${field.name}`}
                                description={field.type === 'checkbox' ? null : field.description}
                                error={form.errors[`values.${field.name}`]}
                            >
                                <FieldRenderer
                                    field={field}
                                    value={form.data.values[field.name]}
                                    onChange={(v) => form.setData('values', { ...form.data.values, [field.name]: v })}
                                    context={{ roles, mediaPreviews }}
                                    error={form.errors[`values.${field.name}`]}
                                />
                            </FormRow>
                        ))}
                    </Card>
                ))}
                <Button type="submit" loading={form.processing}>
                    Save Changes
                </Button>
            </form>
        </AdminLayout>
    );
}
