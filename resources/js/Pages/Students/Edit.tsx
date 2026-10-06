import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { StudentDetails } from '@/Types/people';
import { Head } from '@inertiajs/react';
import StudentForm from './Partials/StudentForm';

export default function Edit({
    student,
    genders,
    statuses,
}: {
    student: StudentDetails;
    genders: Option[];
    statuses: Option[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${student.full_name}`} />

            <PageHeader
                title={`Edit ${student.full_name}`}
                breadcrumbs={[
                    { label: 'Students', href: route('students.index') },
                    { label: student.full_name, href: route('students.show', student.id) },
                    { label: 'Edit' },
                ]}
            />

            <StudentForm student={student} genders={genders} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
