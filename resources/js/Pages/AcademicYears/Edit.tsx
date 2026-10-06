import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AcademicYear } from '@/Types/academics';
import { Head } from '@inertiajs/react';
import YearForm from './Partials/YearForm';

export default function Edit({ year }: { year: AcademicYear }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${year.name}`} />

            <PageHeader
                title={`Edit ${year.name}`}
                breadcrumbs={[
                    { label: 'Academic years', href: route('academic-years.index') },
                    { label: year.name, href: route('academic-years.show', year.id) },
                    { label: 'Edit' },
                ]}
            />

            <YearForm year={year} />
        </AuthenticatedLayout>
    );
}
