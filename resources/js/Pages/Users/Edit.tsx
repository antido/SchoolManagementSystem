import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { UserSummary } from '@/Types/users';
import { Head } from '@inertiajs/react';
import UserForm from './Partials/UserForm';

export default function Edit({
    user,
    roles,
    isSelf,
}: {
    user: UserSummary;
    roles: Option[];
    isSelf: boolean;
}) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${user.name}`} />

            <PageHeader
                title={`Edit ${user.name}`}
                breadcrumbs={[
                    { label: 'Users', href: route('users.index') },
                    { label: user.name, href: route('users.show', user.id) },
                    { label: 'Edit' },
                ]}
            />

            <UserForm user={user} roles={roles} isSelf={isSelf} />
        </AuthenticatedLayout>
    );
}
