import Avatar from '@/Components/Avatar';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import { usePermissions } from '@/Hooks/usePermissions';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { TeacherDetails } from '@/Types/people';
import { formatDate, formatDateTime } from '@/Utils/format';
import { ClassSubject, TeacherAssignmentHistoryItem } from '@/Types/academics';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    teacher,
    assignments,
    classSubjects,
    statuses,
    can,
}: {
    teacher: TeacherDetails;
    assignments: TeacherAssignmentHistoryItem[];
    classSubjects: ClassSubject[];
    statuses: Option[];
    can: { update: boolean; delete: boolean };
}) {
    const [newStatus, setNewStatus] = useState(teacher.status);
    const [confirming, setConfirming] = useState<'status' | 'delete' | null>(null);
    const { busy, send } = useRequest();
    const { can: canDo } = usePermissions();
    const close = () => setConfirming(null);

    const confirm = () => {
        if (confirming === 'status') {
            send('patch', route('teachers.status', teacher.id), { status: newStatus }, { onSuccess: close });
        } else {
            send('delete', route('teachers.destroy', teacher.id), {}, { onSuccess: close });
        }
    };

    const statusLabel = statuses.find((status) => status.value === newStatus)?.label ?? newStatus;
    const keepsSignIn = newStatus === 'active' || newStatus === 'on_leave';

    return (
        <AuthenticatedLayout>
            <Head title={teacher.full_name} />

            <PageHeader
                title={teacher.full_name}
                breadcrumbs={canDo('teachers.view') ? [{ label: 'Teachers', href: route('teachers.index') }, { label: teacher.full_name }] : undefined}
                actions={
                    <>
                        {can.update && (
                            <Button variant="secondary" href={route('teachers.edit', teacher.id)}>
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
                            <Avatar name={teacher.full_name} size="lg" />
                            <div className="min-w-0">
                                <p className="text-lg font-semibold">{teacher.full_name}</p>
                                <p className="font-mono text-sm text-muted">{teacher.employee_number}</p>
                                <div className="mt-2">
                                    <StatusBadge status={teacher.status} />
                                </div>
                            </div>
                        </div>
                        <DetailList
                            items={[
                                { label: 'Email', value: teacher.email },
                                { label: 'Specialization', value: teacher.specialization },
                                { label: 'Contact number', value: teacher.contact_number },
                                { label: 'Date of birth', value: formatDate(teacher.birth_date) },
                                { label: 'Gender', value: teacher.gender_label },
                                {
                                    label: 'Last sign-in',
                                    value: teacher.last_login_at ? formatDateTime(teacher.last_login_at) : 'Never',
                                },
                            ]}
                        />
                    </Card>

                    <Card title="Classes" flush>
                        {assignments.length === 0 ? (
                            <EmptyState icon="class" title="Not assigned to a class yet" message="Class assignments appear here." />
                        ) : (
                            <ul className="divide-y divide-line">
                                {assignments.map((assignment) => (
                                    <li key={assignment.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3">
                                        <Link href={route('classes.show', assignment.class.id)} className="font-medium hover:text-brand hover:underline">
                                            {assignment.class.name}
                                        </Link>
                                        <span className="text-sm text-muted">
                                            {assignment.role_label}, {assignment.academic_year}
                                        </span>
                                        <StatusBadge status={assignment.ended_at ? 'ended' : 'active'} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Subjects taught" flush>
                        {classSubjects.length === 0 ? (
                            <EmptyState icon="book" title="No subjects yet" message="Subjects appear here once the teacher is assigned to teach them." />
                        ) : (
                            <ul className="divide-y divide-line">
                                {classSubjects.map((classSubject) => (
                                    <li key={classSubject.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3">
                                        <Link
                                            href={route('class-subjects.show', classSubject.id)}
                                            className="font-medium hover:text-brand hover:underline"
                                        >
                                            {classSubject.subject.name}
                                        </Link>
                                        <span className="text-sm text-muted">
                                            {classSubject.school_class.name}, {classSubject.term.name}
                                        </span>
                                        <StatusBadge status={classSubject.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {can.update && (
                    <Card title="Status" className="self-start">
                        <p className="text-sm text-muted">
                            Active teachers and teachers on leave can sign in. Inactive and resigned teachers cannot.
                        </p>
                        <label htmlFor="teacher-status" className="mt-4 block text-sm font-medium">
                            Change status to
                        </label>
                        <div className="mt-1 flex gap-2">
                            <SelectInput
                                id="teacher-status"
                                value={newStatus}
                                options={statuses}
                                onChange={(e) => setNewStatus(e.target.value)}
                                className="min-w-0 flex-1"
                            />
                            <Button
                                variant="secondary"
                                disabled={newStatus === teacher.status}
                                onClick={() => setConfirming('status')}
                            >
                                Update
                            </Button>
                        </div>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                show={confirming !== null}
                title={
                    confirming === 'delete'
                        ? `Delete ${teacher.full_name}?`
                        : `Change ${teacher.full_name}'s status to ${statusLabel}?`
                }
                message={
                    confirming === 'delete'
                        ? 'The teacher profile and their sign-in account are removed. This cannot be undone. To keep their history, change their status instead.'
                        : keepsSignIn
                          ? 'The teacher will be able to sign in.'
                          : 'The teacher will no longer be able to sign in. Their records are kept.'
                }
                confirmLabel={confirming === 'delete' ? 'Delete teacher' : 'Change status'}
                tone={confirming === 'delete' ? 'danger' : 'primary'}
                processing={busy}
                onConfirm={confirm}
                onClose={close}
            />
        </AuthenticatedLayout>
    );
}
