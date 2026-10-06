import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import SubjectForm from './Partials/SubjectForm';

export default function Create({ statuses }: { statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Add subject" />
            <PageHeader
                title="Add subject"
                breadcrumbs={[{ label: 'Subjects', href: route('subjects.index') }, { label: 'Add subject' }]}
            />
            <SubjectForm statuses={statuses} />
        </AuthenticatedLayout>
    );
}
