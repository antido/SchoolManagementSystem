import Avatar from '@/Components/Avatar';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import StudentAwardList from '@/Components/StudentAwardList';
import { usePermissions } from '@/Hooks/usePermissions';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { StudentDetails } from '@/Types/people';
import { formatDate, formatDateTime } from '@/Utils/format';
import { EnrollmentHistoryItem } from '@/Types/academics';
import { StudentAward } from '@/Types/awards';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    student,
    enrollments,
    awards,
    statuses,
    can,
}: {
    student: StudentDetails;
    enrollments: EnrollmentHistoryItem[];
    awards: StudentAward[];
    statuses: Option[];
    can: { update: boolean; delete: boolean };
}) {
    const { can: canDo } = usePermissions();
    const [newStatus, setNewStatus] = useState(student.status);
    const [confirming, setConfirming] = useState<'status' | 'delete' | null>(null);
    const { busy, send } = useRequest();
    const close = () => setConfirming(null);

    const confirm = () => {
        if (confirming === 'status') {
            send('patch', route('students.status', student.id), { status: newStatus }, { onSuccess: close });
        } else {
            send('delete', route('students.destroy', student.id), {}, { onSuccess: close });
        }
    };

    const statusLabel = statuses.find((status) => status.value === newStatus)?.label ?? newStatus;

    return (
        <AuthenticatedLayout>
            <Head title={student.full_name} />

            <PageHeader
                title={student.full_name}
                breadcrumbs={canDo('students.view') ? [{ label: 'Students', href: route('students.index') }, { label: student.full_name }] : undefined}
                actions={
                    <>
                        {canDo('attendance.view') && (
                            <Button variant="secondary" href={route('attendance.student', student.id)}>
                                Attendance
                            </Button>
                        )}
                        {can.update && (
                            <Button variant="secondary" href={route('students.edit', student.id)}>
                                Edit
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

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Profile">
                        <div className="mb-6 flex items-center gap-4">
                            <Avatar name={student.full_name} size="lg" />
                            <div className="min-w-0">
                                <p className="text-lg font-semibold">{student.full_name}</p>
                                <p className="font-mono text-sm text-muted">{student.student_number}</p>
                                <div className="mt-2">
                                    <StatusBadge status={student.status} />
                                </div>
                            </div>
                        </div>
                        <DetailList
                            items={[
                                { label: 'Email', value: student.email },
                                { label: 'Contact number', value: student.contact_number },
                                { label: 'Date of birth', value: formatDate(student.birth_date) },
                                { label: 'Gender', value: student.gender_label },
                                { label: 'Address', value: student.address },
                                {
                                    label: 'Last sign-in',
                                    value: student.last_login_at ? formatDateTime(student.last_login_at) : 'Never',
                                },
                            ]}
                        />
                    </Card>

                    <Card title="Classes" flush>
                        {enrollments.length === 0 ? (
                            <EmptyState
                                icon="class"
                                title="Not enrolled in a class yet"
                                message="Classes appear here once the student is enrolled."
                            />
                        ) : (
                            <ul className="divide-y divide-line">
                                {enrollments.map((enrollment) => (
                                    <li key={enrollment.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3">
                                        <Link
                                            href={route('classes.show', enrollment.class.id)}
                                            className="font-medium hover:text-brand hover:underline"
                                        >
                                            {enrollment.class.name}
                                        </Link>
                                        <span className="text-sm text-muted">{enrollment.academic_year}</span>
                                        <StatusBadge status={enrollment.status} />
                                        <span className="w-full text-xs text-muted sm:ml-auto sm:w-auto">
                                            {formatDate(enrollment.enrolled_at)}
                                            {enrollment.ended_at && ` to ${formatDate(enrollment.ended_at)}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <div className="space-y-6">
                    {can.update && (
                        <Card title="Status">
                            <p className="text-sm text-muted">
                                Only active students can sign in and be enrolled in classes.
                            </p>
                            <label htmlFor="student-status" className="mt-4 block text-sm font-medium">
                                Change status to
                            </label>
                            <div className="mt-1 flex gap-2">
                                <SelectInput
                                    id="student-status"
                                    value={newStatus}
                                    options={statuses}
                                    onChange={(e) => setNewStatus(e.target.value)}
                                    className="min-w-0 flex-1"
                                />
                                <Button
                                    variant="secondary"
                                    disabled={newStatus === student.status}
                                    onClick={() => setConfirming('status')}
                                >
                                    Update
                                </Button>
                            </div>
                        </Card>
                    )}

                    {canDo('awards.view') && (
                        <Card title="Awards" flush>
                            {awards.length === 0 ? (
                                <p className="px-5 py-4 text-sm text-muted">No awards yet.</p>
                            ) : (
                                <StudentAwardList awards={awards} />
                            )}
                        </Card>
                    )}
                </div>
            </div>

            <ConfirmDialog
                show={confirming !== null}
                title={
                    confirming === 'delete'
                        ? `Delete ${student.full_name}?`
                        : `Change ${student.full_name}'s status to ${statusLabel}?`
                }
                message={
                    confirming === 'delete'
                        ? 'The student profile and their sign-in account are removed. This cannot be undone. To keep their records, change their status instead.'
                        : newStatus === 'active'
                          ? 'The student will be able to sign in.'
                          : 'The student will no longer be able to sign in. Their records are kept.'
                }
                confirmLabel={confirming === 'delete' ? 'Delete student' : 'Change status'}
                tone={confirming === 'delete' ? 'danger' : 'primary'}
                processing={busy}
                onConfirm={confirm}
                onClose={close}
            />
        </AuthenticatedLayout>
    );
}
