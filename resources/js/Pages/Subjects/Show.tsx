import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ClassSubject, Subject } from '@/Types/academics';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    subject,
    classSubjects,
    can,
}: {
    subject: Subject;
    classSubjects: ClassSubject[];
    can: { update: boolean; delete: boolean };
}) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { busy, send } = useRequest();

    return (
        <AuthenticatedLayout>
            <Head title={subject.name} />

            <PageHeader
                title={subject.name}
                breadcrumbs={[{ label: 'Subjects', href: route('subjects.index') }, { label: subject.name }]}
                actions={
                    <>
                        {can.update && (
                            <Button variant="secondary" href={route('subjects.edit', subject.id)}>
                                Edit
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setConfirmingDelete(true)}>
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="space-y-6">
                <Card>
                    <DetailList
                        items={[
                            { label: 'Code', value: <span className="font-mono">{subject.code}</span> },
                            { label: 'Status', value: <StatusBadge status={subject.status} /> },
                            { label: 'Description', value: subject.description },
                        ]}
                    />
                </Card>

                <Card title="Classes taking this subject" flush>
                    {classSubjects.length === 0 ? (
                        <EmptyState icon="class" title="Not taught to any class yet" message="Add it to a class from the class's page." />
                    ) : (
                        <Table caption="Classes taking this subject">
                            <thead>
                                <tr>
                                    <Th>Class</Th>
                                    <Th>Term</Th>
                                    <Th>Teacher</Th>
                                    <Th>Status</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {classSubjects.map((classSubject) => (
                                    <tr key={classSubject.id}>
                                        <Td>
                                            <Link
                                                href={route('class-subjects.show', classSubject.id)}
                                                className="font-semibold hover:text-brand hover:underline"
                                            >
                                                {classSubject.school_class.name}
                                            </Link>
                                        </Td>
                                        <Td>{classSubject.term.name}</Td>
                                        <Td>{classSubject.teacher?.name ?? <span className="text-muted">Not assigned</span>}</Td>
                                        <Td>
                                            <StatusBadge status={classSubject.status} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                show={confirmingDelete}
                title={`Delete ${subject.name}?`}
                message="This cannot be undone."
                confirmLabel="Delete subject"
                processing={busy}
                onConfirm={() => send('delete', route('subjects.destroy', subject.id))}
                onClose={() => setConfirmingDelete(false)}
            />
        </AuthenticatedLayout>
    );
}
