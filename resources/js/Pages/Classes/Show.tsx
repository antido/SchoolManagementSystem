import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { usePermissions } from '@/Hooks/usePermissions';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { ClassSubject, ClassTeachers, EnrollmentCandidate, Roster, SchoolClassDetails } from '@/Types/academics';
import ClassTeachersCard from './Partials/ClassTeachersCard';
import RosterCard from './Partials/RosterCard';
import ClassSubjectsCard, { ClassSubjectOptions } from './Partials/ClassSubjectsCard';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    schoolClass,
    classSubjects,
    options,
    roster,
    candidates,
    teachers,
    teacherOptions,
    can,
}: {
    schoolClass: SchoolClassDetails;
    classSubjects: ClassSubject[];
    options: ClassSubjectOptions | null;
    roster: Roster | null;
    candidates?: EnrollmentCandidate[];
    teachers: ClassTeachers;
    teacherOptions: { teachers: Option<number>[]; roles: Option[] } | null;
    can: {
        update: boolean;
        delete: boolean;
        addSubject: boolean;
        updateSubjects: boolean;
        enroll: boolean;
        withdraw: boolean;
        assignTeacher: boolean;
        endAssignment: boolean;
    };
}) {
    const { can: canDo, hasRole } = usePermissions();
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { busy, send } = useRequest();

    return (
        <AuthenticatedLayout>
            <Head title={schoolClass.name} />

            <PageHeader
                title={schoolClass.name}
                breadcrumbs={[{ label: 'Classes', href: route('classes.index') }, { label: schoolClass.name }]}
                actions={
                    <>
                        {canDo('attendance.view') && !hasRole('student') && (
                            <Button variant="secondary" href={route('attendance.sheet', schoolClass.id)}>
                                Attendance
                            </Button>
                        )}
                        {can.update && (
                            <Button variant="secondary" href={route('classes.edit', schoolClass.id)}>
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
                <Card title="Academic context">
                    <DetailList
                        items={[
                            {
                                label: 'Academic year',
                                value: (
                                    <span className="inline-flex items-center gap-2">
                                        {schoolClass.academic_year.name}
                                        {schoolClass.academic_year.is_active && <Badge tone="success">Active</Badge>}
                                    </span>
                                ),
                            },
                            { label: 'Code', value: <span className="font-mono">{schoolClass.code}</span> },
                            { label: 'Grade level', value: schoolClass.grade_level },
                            { label: 'Section', value: schoolClass.section },
                            { label: 'Status', value: <StatusBadge status={schoolClass.status} /> },
                            { label: 'Description', value: schoolClass.description },
                        ]}
                    />
                </Card>

                <ClassSubjectsCard
                    classId={schoolClass.id}
                    classSubjects={classSubjects}
                    options={options}
                    canAdd={can.addSubject}
                    canUpdate={can.updateSubjects}
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <ClassTeachersCard
                        classId={schoolClass.id}
                        teachers={teachers}
                        options={teacherOptions}
                        canAssign={can.assignTeacher}
                        canEnd={can.endAssignment}
                    />
                    {roster && (
                        <RosterCard
                            classId={schoolClass.id}
                            roster={roster}
                            candidates={candidates}
                            canEnroll={can.enroll}
                            canWithdraw={can.withdraw}
                        />
                    )}
                </div>
            </div>

            <ConfirmDialog
                show={confirmingDelete}
                title={`Delete ${schoolClass.name}?`}
                message="This cannot be undone."
                confirmLabel="Delete class"
                processing={busy}
                onConfirm={() => send('delete', route('classes.destroy', schoolClass.id))}
                onClose={() => setConfirmingDelete(false)}
            />
        </AuthenticatedLayout>
    );
}
