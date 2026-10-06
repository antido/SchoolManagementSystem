import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/Types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdateAvatarForm from './Partials/UpdateAvatarForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    canDeleteAccount,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; canDeleteAccount: boolean }>) {
    return (
        <AuthenticatedLayout>
            <Head title="My profile" />

            <PageHeader title="My profile" />

            <div className="max-w-3xl space-y-6">
                <Card>
                    <UpdateAvatarForm />
                </Card>
                <Card>
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </Card>
                <Card>
                    <UpdatePasswordForm />
                </Card>
                {canDeleteAccount ? (
                    <Card>
                        <DeleteUserForm />
                    </Card>
                ) : (
                    <p className="text-sm text-muted">
                        Your account is part of the school's records, so it cannot be deleted here. Ask an administrator if it should be closed.
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
