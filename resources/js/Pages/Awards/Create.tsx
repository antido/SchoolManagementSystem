import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import AwardForm from './Partials/AwardForm';

export default function Create({ statuses }: { statuses: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Add award" />
            <PageHeader title="Add award" breadcrumbs={[{ label: 'Awards', href: route('awards.index') }, { label: 'Add award' }]} />
            <AwardForm statuses={statuses} />
        </AuthenticatedLayout>
    );
}
