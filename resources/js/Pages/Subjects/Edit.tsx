import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Subject } from '@/Types/academics';
import { Head } from '@inertiajs/react';
import SubjectForm from './Partials/SubjectForm';

export default function Edit({ subject, statuses }: { subject: Subject; statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${subject.name}`} />
            <PageHeader
                title={`Edit ${subject.name}`}
                breadcrumbs={[
                    { label: 'Subjects', href: route('subjects.index') },
                    { label: subject.name, href: route('subjects.show', subject.id) },
                    { label: 'Edit' },
                ]}
            />
            <SubjectForm subject={subject} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
