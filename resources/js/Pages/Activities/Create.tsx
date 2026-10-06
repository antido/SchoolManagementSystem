import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import ActivityForm from './Partials/ActivityForm';

export default function Create({
    classSubjects,
    types,
    defaults,
}: {
    classSubjects: Option<number>[];
    types: Option[];
    defaults: { class_subject_id: number | null; type: string | null };
}) {
    return (
        <AuthenticatedLayout>
            <Head title="New activity" />
            <PageHeader
                title="New activity"
                intro="It is saved as a draft. Students see it once you publish it."
                breadcrumbs={[{ label: 'Activities', href: route('activities.index') }, { label: 'New activity' }]}
            />

            {classSubjects.length === 0 ? (
                <Card className="max-w-3xl">
                    <EmptyState
                        icon="book"
                        title="No subjects to add activities to"
                        message="You can add activities to the active class subjects you teach. Ask an administrator to assign you to one."
                    />
                </Card>
            ) : (
                <ActivityForm classSubjects={classSubjects} types={types} defaults={defaults} />
            )}
        </AuthenticatedLayout>
    );
}
