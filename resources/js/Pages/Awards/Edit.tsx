import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Award } from '@/Types/awards';
import { Head } from '@inertiajs/react';
import AwardForm from './Partials/AwardForm';

export default function Edit({ award, statuses }: { award: Award; statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${award.name}`} />
            <PageHeader
                title={`Edit ${award.name}`}
                breadcrumbs={[
                    { label: 'Awards', href: route('awards.index') },
                    { label: award.name, href: route('awards.show', award.id) },
                    { label: 'Edit' },
                ]}
            />
            <AwardForm award={award} statuses={statuses} />
        </AuthenticatedLayout>
    );
}
