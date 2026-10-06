import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import FormField from '@/Components/FormField';
import Modal from '@/Components/Modal';
import SelectInput from '@/Components/SelectInput';
import { usePermissions } from '@/Hooks/usePermissions';
import { useRequest } from '@/Hooks/useRequest';
import { Option } from '@/Types';
import { ClassTeacherAssignment, ClassTeachers } from '@/Types/academics';
import { formatDate } from '@/Utils/format';
import { DialogTitle } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** A teacher's name, linked to their profile for people who may open it. */
function TeacherName({ id, name }: { id: number; name: string }) {
    const { can } = usePermissions();

    if (!can('teachers.view')) {
        return <span className="font-medium">{name}</span>;
    }

    return (
        <Link href={route('teachers.show', id)} className="font-medium hover:text-brand hover:underline">
            {name}
        </Link>
    );
}

/**
 * The class's adviser and teachers, plus who teaches its subjects.
 * Ended assignments are listed separately so the history stays visible.
 */
export default function ClassTeachersCard({
    classId,
    teachers,
    options,
    canAssign,
    canEnd,
}: {
    classId: number;
    teachers: ClassTeachers;
    options: { teachers: Option<number>[]; roles: Option[] } | null;
    canAssign: boolean;
    canEnd: boolean;
}) {
    const [assigning, setAssigning] = useState(false);
    const [ending, setEnding] = useState<ClassTeacherAssignment | null>(null);
    const [showPast, setShowPast] = useState(false);
    const { busy, send } = useRequest();

    const isEmpty = teachers.current.length === 0 && teachers.subject_teachers.length === 0;

    return (
        <Card
            title="Teachers"
            flush
            actions={
                canAssign &&
                options && (
                    <Button small onClick={() => setAssigning(true)}>
                        Assign teacher
                    </Button>
                )
            }
        >
            {isEmpty ? (
                <EmptyState icon="teacher" title="No teachers assigned yet" message="Assign an adviser, or add subjects with their teachers." />
            ) : (
                <ul className="divide-y divide-line">
                    {teachers.current.map((assignment) => (
                        <li key={assignment.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                            <div className="min-w-0 flex-1">
                                <TeacherName id={assignment.teacher.id} name={assignment.teacher.name} />
                                <p className="text-xs text-muted">Since {formatDate(assignment.assigned_at)}</p>
                            </div>
                            <Badge tone={assignment.role === 'adviser' ? 'brand' : 'neutral'}>{assignment.role_label}</Badge>
                            {canEnd && (
                                <Button small variant="ghost" onClick={() => setEnding(assignment)}>
                                    End
                                </Button>
                            )}
                        </li>
                    ))}
                    {teachers.subject_teachers.map((entry) => (
                        <li key={`subject-${entry.teacher.id}`} className="px-5 py-3">
                            <TeacherName id={entry.teacher.id} name={entry.teacher.name} />
                            <p className="text-xs text-muted">Teaches {entry.subjects.join(', ')}</p>
                        </li>
                    ))}
                </ul>
            )}

            {teachers.past.length > 0 && (
                <div className="border-t border-line px-5 py-3">
                    <button
                        type="button"
                        className="text-sm font-semibold text-brand hover:underline"
                        aria-expanded={showPast}
                        onClick={() => setShowPast(!showPast)}
                    >
                        {showPast ? 'Hide' : 'Show'} past assignments ({teachers.past.length})
                    </button>
                    {showPast && (
                        <ul className="mt-3 space-y-2 text-sm">
                            {teachers.past.map((assignment) => (
                                <li key={assignment.id}>
                                    {assignment.teacher.name}, {assignment.role_label.toLowerCase()},{' '}
                                    <span className="text-muted">
                                        {formatDate(assignment.assigned_at)} to {formatDate(assignment.ended_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {options && <AssignTeacherModal show={assigning} classId={classId} options={options} onClose={() => setAssigning(false)} />}

            <ConfirmDialog
                show={ending !== null}
                title={`End ${ending?.teacher.name}'s assignment?`}
                message="They are no longer listed as a current teacher of this class. The assignment stays in the history."
                confirmLabel="End assignment"
                processing={busy}
                onConfirm={() => ending && send('patch', route('class-teachers.end', ending.id), {}, { onSuccess: () => setEnding(null) })}
                onClose={() => setEnding(null)}
            />
        </Card>
    );
}

function AssignTeacherModal({
    show,
    classId,
    options,
    onClose,
}: {
    show: boolean;
    classId: number;
    options: { teachers: Option<number>[]; roles: Option[] };
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ teacher_id: '', role: 'teacher' });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('class-teachers.store', classId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal show={show} onClose={close} maxWidth="md">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">Assign a teacher</DialogTitle>
                <FormField id="ct-teacher" label="Teacher" error={errors.teacher_id} required>
                    <SelectInput
                        value={data.teacher_id}
                        options={options.teachers}
                        placeholder="Choose a teacher"
                        onChange={(e) => setData('teacher_id', e.target.value)}
                    />
                </FormField>
                <FormField id="ct-role" label="Role" error={errors.role} hint="A class can have one adviser." required>
                    <SelectInput value={data.role} options={options.roles} onChange={(e) => setData('role', e.target.value)} />
                </FormField>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Assigning…' : 'Assign'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
