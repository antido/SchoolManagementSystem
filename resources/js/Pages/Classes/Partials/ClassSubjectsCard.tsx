import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import FormField from '@/Components/FormField';
import Modal from '@/Components/Modal';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import { useRequest } from '@/Hooks/useRequest';
import { Option } from '@/Types';
import { ClassSubject } from '@/Types/academics';
import { DialogTitle } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export interface ClassSubjectOptions {
    subjects: Option<number>[];
    terms: Option<number>[];
    teachers: Option<number>[];
}

/**
 * The "Subjects" section of a class page: what the class takes in each
 * term and who teaches it, with dialogs to add a subject or change its teacher.
 */
export default function ClassSubjectsCard({
    classId,
    classSubjects,
    options,
    canAdd,
    canUpdate,
}: {
    classId: number;
    classSubjects: ClassSubject[];
    options: ClassSubjectOptions | null;
    canAdd: boolean;
    canUpdate: boolean;
}) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<ClassSubject | null>(null);
    const [removing, setRemoving] = useState<ClassSubject | null>(null);
    const { busy, send } = useRequest();

    return (
        <Card
            title="Subjects"
            flush
            actions={
                canAdd &&
                options && (
                    <Button small onClick={() => setAdding(true)}>
                        Add subject
                    </Button>
                )
            }
        >
            {classSubjects.length === 0 ? (
                <EmptyState
                    icon="book"
                    title="No subjects yet"
                    message="Add the subjects this class takes in each term, and who teaches them."
                />
            ) : (
                <Table caption="Subjects taken by this class">
                    <thead>
                        <tr>
                            <Th>Subject</Th>
                            <Th>Term</Th>
                            <Th>Teacher</Th>
                            <Th>Status</Th>
                            {(canUpdate || classSubjects.some((item) => item.can_delete)) && (
                                <Th>
                                    <span className="sr-only">Actions</span>
                                </Th>
                            )}
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
                                        {classSubject.subject.name}
                                    </Link>
                                    <p className="font-mono text-xs text-muted">{classSubject.subject.code}</p>
                                </Td>
                                <Td>{classSubject.term.name}</Td>
                                <Td>{classSubject.teacher?.name ?? <span className="text-muted">Not assigned</span>}</Td>
                                <Td>
                                    <StatusBadge status={classSubject.status} />
                                </Td>
                                {(canUpdate || classSubject.can_delete) && (
                                    <Td className="text-right whitespace-nowrap">
                                        {canUpdate && options && (
                                            <Button small variant="ghost" onClick={() => setEditing(classSubject)}>
                                                Change
                                            </Button>
                                        )}
                                        {classSubject.can_delete && (
                                            <Button small variant="ghost" onClick={() => setRemoving(classSubject)}>
                                                Remove
                                            </Button>
                                        )}
                                    </Td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}

            {options && <AddSubjectModal show={adding} classId={classId} options={options} onClose={() => setAdding(false)} />}
            {options && <EditClassSubjectModal classSubject={editing} options={options} onClose={() => setEditing(null)} />}

            <ConfirmDialog
                show={removing !== null}
                title={`Remove ${removing?.subject.name} from this class?`}
                message={`It is no longer taught in ${removing?.term.name}.`}
                confirmLabel="Remove subject"
                processing={busy}
                onConfirm={() =>
                    removing && send('delete', route('class-subjects.destroy', removing.id), {}, { onSuccess: () => setRemoving(null) })
                }
                onClose={() => setRemoving(null)}
            />
        </Card>
    );
}

function AddSubjectModal({
    show,
    classId,
    options,
    onClose,
}: {
    show: boolean;
    classId: number;
    options: ClassSubjectOptions;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        subject_id: '',
        academic_term_id: String(options.terms[0]?.value ?? ''),
        teacher_id: '',
    });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('class-subjects.store', classId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal show={show} onClose={close} maxWidth="lg">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">Add a subject</DialogTitle>

                {options.terms.length === 0 ? (
                    <p className="text-sm text-muted">This class's academic year has no terms yet. Add a term to the year first.</p>
                ) : (
                    <>
                        <FormField id="cs-subject" label="Subject" error={errors.subject_id} required>
                            <SelectInput
                                value={data.subject_id}
                                options={options.subjects}
                                placeholder="Choose a subject"
                                onChange={(e) => setData('subject_id', e.target.value)}
                            />
                        </FormField>
                        <FormField id="cs-term" label="Term" error={errors.academic_term_id} required>
                            <SelectInput
                                value={data.academic_term_id}
                                options={options.terms}
                                onChange={(e) => setData('academic_term_id', e.target.value)}
                            />
                        </FormField>
                        <FormField id="cs-teacher" label="Teacher" error={errors.teacher_id} hint="You can assign a teacher later.">
                            <SelectInput
                                value={data.teacher_id}
                                options={options.teachers}
                                placeholder="Not assigned yet"
                                onChange={(e) => setData('teacher_id', e.target.value)}
                            />
                        </FormField>
                    </>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing || options.terms.length === 0}>
                        {processing ? 'Adding…' : 'Add subject'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function EditClassSubjectModal({
    classSubject,
    options,
    onClose,
}: {
    classSubject: ClassSubject | null;
    options: ClassSubjectOptions;
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        teacher_id: '',
        status: 'active',
    });

    useEffect(() => {
        if (classSubject) {
            clearErrors();
            setData({
                teacher_id: String(classSubject.teacher?.id ?? ''),
                status: classSubject.status,
            });
        }
    }, [classSubject]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (classSubject) {
            put(route('class-subjects.update', classSubject.id), { preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <Modal show={classSubject !== null} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">
                    {classSubject?.subject.name}, {classSubject?.term.name}
                </DialogTitle>
                <FormField id="cs-edit-teacher" label="Teacher" error={errors.teacher_id}>
                    <SelectInput
                        value={data.teacher_id}
                        options={options.teachers}
                        placeholder="Not assigned"
                        onChange={(e) => setData('teacher_id', e.target.value)}
                    />
                </FormField>
                <FormField id="cs-edit-status" label="Status" error={errors.status} required>
                    <SelectInput
                        value={data.status}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]}
                        onChange={(e) => setData('status', e.target.value)}
                    />
                </FormField>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
