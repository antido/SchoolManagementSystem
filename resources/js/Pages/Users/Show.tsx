import Avatar from '@/Components/Avatar';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { UserSummary } from '@/Types/users';
import { formatDate, formatDateTime } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Action = 'deactivate' | 'activate' | 'reset' | 'delete' | null;

export default function Show({
    user,
    can,
}: {
    user: UserSummary;
    can: { update: boolean; changeStatus: boolean; resetPassword: boolean; delete: boolean };
}) {
    const [confirming, setConfirming] = useState<Action>(null);
    const { busy, send } = useRequest();
    const close = () => setConfirming(null);

    const run = () => {
        const options = { onSuccess: close };

        if (confirming === 'deactivate' || confirming === 'activate') {
            send('patch', route('users.status', user.id), { is_active: confirming === 'activate' }, options);
        } else if (confirming === 'reset') {
            send('post', route('users.password-reset', user.id), {}, options);
        } else if (confirming === 'delete') {
            send('delete', route('users.destroy', user.id), {}, options);
        }
    };

    const dialogs: Record<Exclude<Action, null>, { title: string; message: string; label: string; tone: 'danger' | 'primary' }> = {
        deactivate: {
            title: `Deactivate ${user.name}?`,
            message: 'They are signed out and cannot sign in until the account is activated again. Their records are kept.',
            label: 'Deactivate account',
            tone: 'danger',
        },
        activate: {
            title: `Activate ${user.name}?`,
            message: 'They will be able to sign in again.',
            label: 'Activate account',
            tone: 'primary',
        },
        reset: {
            title: 'Send a password reset link?',
            message: `An email with a link to choose a new password is sent to ${user.email}. The current password keeps working until they change it.`,
            label: 'Send link',
            tone: 'primary',
        },
        delete: {
            title: `Delete ${user.name}'s account?`,
            message: 'This cannot be undone. To keep their history, deactivate the account instead.',
            label: 'Delete account',
            tone: 'danger',
        },
    };

    const dialog = confirming ? dialogs[confirming] : null;

    return (
        <AuthenticatedLayout>
            <Head title={user.name} />

            <PageHeader
                title={user.name}
                breadcrumbs={[{ label: 'Users', href: route('users.index') }, { label: user.name }]}
                actions={
                    <>
                        {can.update && (
                            <Button variant="secondary" href={route('users.edit', user.id)}>
                                Edit
                            </Button>
                        )}
                        {can.resetPassword && (
                            <Button variant="secondary" onClick={() => setConfirming('reset')}>
                                Send password reset
                            </Button>
                        )}
                        {can.changeStatus && (
                            <Button
                                variant="secondary"
                                onClick={() => setConfirming(user.is_active ? 'deactivate' : 'activate')}
                            >
                                {user.is_active ? 'Deactivate' : 'Activate'}
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setConfirming('delete')}>
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <Card className="max-w-2xl">
                <div className="flex items-center gap-4">
                    <Avatar name={user.name} size="lg" />
                    <div className="min-w-0">
                        <p className="truncate text-lg font-semibold">{user.name}</p>
                        <p className="truncate text-muted">{user.email}</p>
                    </div>
                </div>

                <dl className="mt-6 grid gap-x-6 gap-y-4 border-t border-line pt-5 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted">Role</dt>
                        <dd className="mt-0.5 font-medium">{user.role_label ?? 'None'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">Status</dt>
                        <dd className="mt-1">
                            <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">Last sign-in</dt>
                        <dd className="mt-0.5 font-medium">
                            {user.last_login_at ? formatDateTime(user.last_login_at) : 'Never'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">Account created</dt>
                        <dd className="mt-0.5 font-medium">{formatDate(user.created_at)}</dd>
                    </div>
                </dl>
            </Card>

            <ConfirmDialog
                show={dialog !== null}
                title={dialog?.title ?? ''}
                message={dialog?.message}
                confirmLabel={dialog?.label}
                tone={dialog?.tone}
                processing={busy}
                onConfirm={run}
                onClose={close}
            />
        </AuthenticatedLayout>
    );
}
