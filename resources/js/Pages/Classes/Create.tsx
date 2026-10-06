import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import ClassForm from './Partials/ClassForm';

export default function Create({
    years,
    statuses,
    defaultYearId,
}: {
    years: Option<number>[];
    statuses: Option[];
    defaultYearId: number | null;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="New class" />

            <PageHeader
                title="New class"
                breadcrumbs={[{ label: 'Classes', href: route('classes.index') }, { label: 'New class' }]}
            />

            <ClassForm years={years} statuses={statuses} defaultYearId={defaultYearId} />
        </AuthenticatedLayout>
    );
}
