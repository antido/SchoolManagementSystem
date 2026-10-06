import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import TeacherForm from './Partials/TeacherForm';

export default function Create({ genders, statuses }: { genders: Option[]; statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Add teacher" />

            <PageHeader
                title="Add teacher"
                intro="This also creates the account the teacher signs in with."
                breadcrumbs={[{ label: 'Teachers', href: route('teachers.index') }, { label: 'Add teacher' }]}
            />

            <TeacherForm genders={genders} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
