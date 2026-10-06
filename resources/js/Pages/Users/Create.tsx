import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head } from '@inertiajs/react';
import UserForm from './Partials/UserForm';

export default function Create({ roles }: { roles: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="New account" />

            <PageHeader
                title="New account"
                breadcrumbs={[{ label: 'Users', href: route('users.index') }, { label: 'New account' }]}
            />

            <UserForm roles={roles} />
        </AuthenticatedLayout>
    );
}
