import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { SchoolClassDetails } from '@/Types/academics';
import { Head } from '@inertiajs/react';
import ClassForm from './Partials/ClassForm';

export default function Edit({
    schoolClass,
    years,
    statuses,
}: {
    schoolClass: SchoolClassDetails;
    years: Option<number>[];
    statuses: Option[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${schoolClass.name}`} />

            <PageHeader
                title={`Edit ${schoolClass.name}`}
                breadcrumbs={[
                    { label: 'Classes', href: route('classes.index') },
                    { label: schoolClass.name, href: route('classes.show', schoolClass.id) },
                    { label: 'Edit' },
                ]}
            />

            <ClassForm schoolClass={schoolClass} years={years} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
