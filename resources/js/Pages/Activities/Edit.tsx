import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { ActivityDetails } from '@/Types/activities';
import { Head } from '@inertiajs/react';
import ActivityForm from './Partials/ActivityForm';

export default function Edit({ activity, types }: { activity: ActivityDetails; types: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${activity.title}`} />
            <PageHeader
                title={`Edit ${activity.title}`}
                intro={`${activity.class_subject.subject}, ${activity.class_subject.school_class.name}`}
                breadcrumbs={[
                    { label: 'Activities', href: route('activities.index') },
                    { label: activity.title, href: route('activities.show', activity.id) },
                    { label: 'Edit' },
                ]}
            />
            <ActivityForm activity={activity} types={types} />
        </AuthenticatedLayout>
    );
}
