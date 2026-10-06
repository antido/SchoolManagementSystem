import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import StudentForm from './Partials/StudentForm';

export default function Create({ genders, statuses }: { genders: Option[]; statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Add student" />

            <PageHeader
                title="Add student"
                intro="This also creates the account the student signs in with."
                breadcrumbs={[{ label: 'Students', href: route('students.index') }, { label: 'Add student' }]}
            />

            <StudentForm genders={genders} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
