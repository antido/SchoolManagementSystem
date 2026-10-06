import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { TeacherDetails } from '@/Types/people';
import { Head } from '@inertiajs/react';
import TeacherForm from './Partials/TeacherForm';

export default function Edit({
    teacher,
    genders,
    statuses,
}: {
    teacher: TeacherDetails;
    genders: Option[];
    statuses: Option[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${teacher.full_name}`} />

            <PageHeader
                title={`Edit ${teacher.full_name}`}
                breadcrumbs={[
                    { label: 'Teachers', href: route('teachers.index') },
                    { label: teacher.full_name, href: route('teachers.show', teacher.id) },
                    { label: 'Edit' },
                ]}
            />

            <TeacherForm teacher={teacher} genders={genders} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
