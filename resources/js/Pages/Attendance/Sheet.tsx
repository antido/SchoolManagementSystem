import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { SchoolClassSummary } from '@/Types/academics';
import { cn, formatDate } from '@/Utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Row {
    student: { id: number; name: string; student_number: string };
    status: string | null;
    remarks: string | null;
}

/** Colours of the status buttons when chosen. */
const chosenStyle: Record<string, string> = {
    present: 'border-success bg-success text-white',
    late: 'border-warning bg-warning text-white',
    absent: 'border-danger bg-danger text-white',
    excused: 'border-brand bg-brand text-white',
};

/**
 * The attendance sheet: a class (or one of its subjects) on one day. Pick
 * a status for each student, or mark everyone present and change the few
 * who were not.
 */
export default function Sheet({
    schoolClass,
    yearRange,
    date,
    classSubjectId,
    subjects,
    rows,
    recorded,
    statuses,
    can,
}: {
    schoolClass: SchoolClassSummary;
    yearRange: { start: string; end: string };
    date: string;
    classSubjectId: number | null;
    subjects: Option<number>[];
    rows: Row[];
    recorded: boolean;
    statuses: Option[];
    can: { record: boolean };
}) {
    const { data, setData, put, processing, errors } = useForm({
        date,
        class_subject_id: classSubjectId,
        records: rows.map((row) => ({ student_id: row.student.id, status: row.status ?? '', remarks: row.remarks ?? '' })),
    });

    const today = new Date().toISOString().slice(0, 10);
    const maxDate = yearRange.end < today ? yearRange.end : today;

    // Changing the day or subject loads that sheet.
    const open = (changes: { date?: string; class_subject?: number | string | null }) =>
        router.get(
            route('attendance.sheet', schoolClass.id),
            { date, class_subject: classSubjectId ?? undefined, ...changes },
            { preserveScroll: true },
        );

    const setRecord = (index: number, changes: Partial<(typeof data.records)[number]>) =>
        setData(
            'records',
            data.records.map((record, i) => (i === index ? { ...record, ...changes } : record)),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('attendance.store', schoolClass.id), { preserveScroll: true });
    };

    const unmarked = data.records.filter((record) => !record.status).length;
    const firstRecordError = Object.entries(errors).find(([key]) => key.startsWith('records'))?.[1];

    return (
        <AuthenticatedLayout>
            <Head title={`Attendance, ${schoolClass.name}`} />

            <PageHeader
                title={`Attendance: ${schoolClass.name}`}
                intro={recorded ? `Recorded for ${formatDate(date)}. You can correct it below.` : `Not recorded yet for ${formatDate(date)}.`}
                breadcrumbs={[
                    { label: 'Attendance', href: route('attendance.index') },
                    { label: schoolClass.name, href: route('classes.show', schoolClass.id) },
                    { label: 'Sheet' },
                ]}
                actions={
                    <Button variant="secondary" href={route('attendance.summary', [schoolClass.id, { class_subject: classSubjectId ?? undefined }])}>
                        Summary
                    </Button>
                }
            />

            <Card flush>
                <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-end">
                    <div>
                        <label htmlFor="sheet-date" className="text-sm font-medium">
                            Day
                        </label>
                        <TextInput
                            id="sheet-date"
                            type="date"
                            value={date}
                            min={yearRange.start}
                            max={maxDate}
                            onChange={(e) => e.target.value && open({ date: e.target.value })}
                            className="mt-1 block"
                        />
                    </div>
                    <div className="min-w-0 flex-1 sm:max-w-sm">
                        <label htmlFor="sheet-subject" className="text-sm font-medium">
                            For
                        </label>
                        <SelectInput
                            id="sheet-subject"
                            value={classSubjectId ?? ''}
                            options={subjects}
                            placeholder="The whole day (daily attendance)"
                            onChange={(e) => open({ class_subject: e.target.value || null })}
                            className="mt-1 w-full"
                        />
                    </div>
                    {can.record && rows.length > 0 && (
                        <Button
                            variant="secondary"
                            className="sm:ml-auto"
                            onClick={() =>
                                setData(
                                    'records',
                                    data.records.map((record) => ({ ...record, status: record.status || 'present' })),
                                )
                            }
                        >
                            Mark the rest present
                        </Button>
                    )}
                </div>

                {rows.length === 0 ? (
                    <EmptyState icon="student" title="No students in this class" message="Enroll students before taking attendance." />
                ) : (
                    <form onSubmit={submit}>
                        <ul className="divide-y divide-line">
                            {rows.map((row, index) => {
                                const record = data.records[index];

                                return (
                                    <li key={row.student.id} className="flex flex-col gap-3 px-5 py-3 lg:flex-row lg:items-center">
                                        <div className="min-w-0 lg:w-64">
                                            <p className="font-medium">{row.student.name}</p>
                                            <p className="font-mono text-xs text-muted">{row.student.student_number}</p>
                                        </div>

                                        {can.record ? (
                                            <>
                                                <fieldset className="flex flex-wrap gap-2">
                                                    <legend className="sr-only">Attendance of {row.student.name}</legend>
                                                    {statuses.map((status) => (
                                                        <label
                                                            key={status.value}
                                                            className={cn(
                                                                'cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-medium has-focus-visible:ring-2 has-focus-visible:ring-pencil',
                                                                record.status === status.value ? chosenStyle[status.value] : 'border-line bg-surface hover:bg-canvas',
                                                            )}
                                                        >
                                                            <input
                                                                type="radio"
                                                                name={`status-${row.student.id}`}
                                                                value={status.value}
                                                                checked={record.status === status.value}
                                                                onChange={() => setRecord(index, { status: status.value })}
                                                                className="sr-only"
                                                            />
                                                            {status.label}
                                                        </label>
                                                    ))}
                                                </fieldset>
                                                <TextInput
                                                    aria-label={`Remarks for ${row.student.name}`}
                                                    placeholder="Remarks"
                                                    value={record.remarks}
                                                    onChange={(e) => setRecord(index, { remarks: e.target.value })}
                                                    className="min-w-0 flex-1"
                                                />
                                            </>
                                        ) : (
                                            <div className="flex items-center gap-3">
                                                {row.status ? <StatusBadge status={row.status} /> : <span className="text-sm text-muted">Not recorded</span>}
                                                {row.remarks && <span className="text-sm text-muted">{row.remarks}</span>}
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>

                        {can.record && (
                            <div className="sticky bottom-0 flex flex-col gap-3 border-t border-line bg-surface/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center">
                                <PrimaryButton disabled={processing || unmarked > 0}>{processing ? 'Saving…' : 'Save attendance'}</PrimaryButton>
                                <p className="text-sm text-muted">
                                    {unmarked > 0 ? `${unmarked} ${unmarked === 1 ? 'student is' : 'students are'} not marked yet.` : 'Everyone is marked.'}
                                </p>
                                <InputError message={errors.date ?? firstRecordError} />
                            </div>
                        )}
                    </form>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
