import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import YearForm from './Partials/YearForm';

export default function Create() {
    return (
        <AuthenticatedLayout>
            <Head title="New academic year" />

            <PageHeader
                title="New academic year"
                breadcrumbs={[
                    { label: 'Academic years', href: route('academic-years.index') },
                    { label: 'New academic year' },
                ]}
            />

            <YearForm />
        </AuthenticatedLayout>
    );
}
