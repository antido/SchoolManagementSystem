import Avatar from '@/Components/Avatar';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import { useRequest } from '@/Hooks/useRequest';
import { EnrollmentCandidate, Roster, RosterEntry } from '@/Types/academics';
import { formatDate } from '@/Utils/format';
import { DialogTitle } from '@headlessui/react';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

/**
 * The class roster: current students, a dialog to enroll more, and a
 * collapsible list of students who have left the class.
 */
export default function RosterCard({
    classId,
    roster,
    candidates,
    canEnroll,
    canWithdraw,
}: {
    classId: number;
    roster: Roster;
    candidates?: EnrollmentCandidate[];
    canEnroll: boolean;
    canWithdraw: boolean;
}) {
    const [enrolling, setEnrolling] = useState(false);
    const [withdrawing, setWithdrawing] = useState<RosterEntry | null>(null);
    const [showPast, setShowPast] = useState(false);
    const { busy, send } = useRequest();

    return (
        <Card
            title={`Students (${roster.current.length})`}
            flush
            actions={
                canEnroll && (
                    <Button small onClick={() => setEnrolling(true)}>
                        Enroll students
                    </Button>
                )
            }
        >
            {roster.current.length === 0 ? (
                <EmptyState icon="student" title="No students enrolled yet" message="Enroll students to build the class roster." />
            ) : (
                <ul className="divide-y divide-line">
                    {roster.current.map((entry) => (
                        <li key={entry.id} className="flex items-center gap-3 px-5 py-3">
                            <Avatar name={entry.student.full_name} size="sm" />
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={route('students.show', entry.student.id)}
                                    className="font-medium hover:text-brand hover:underline"
                                >
                                    {entry.student.sortable_name}
                                </Link>
                                <p className="text-xs text-muted">
                                    {entry.student.student_number}, enrolled {formatDate(entry.enrolled_at)}
                                </p>
                            </div>
                            {canWithdraw && (
                                <Button small variant="ghost" onClick={() => setWithdrawing(entry)}>
                                    Withdraw
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {roster.past.length > 0 && (
                <div className="border-t border-line px-5 py-3">
                    <button
                        type="button"
                        className="text-sm font-semibold text-brand hover:underline"
                        aria-expanded={showPast}
                        onClick={() => setShowPast(!showPast)}
                    >
                        {showPast ? 'Hide' : 'Show'} past students ({roster.past.length})
                    </button>
                    {showPast && (
                        <ul className="mt-3 space-y-2">
                            {roster.past.map((entry) => (
                                <li key={entry.id} className="flex flex-wrap items-center gap-2 text-sm">
                                    <Link href={route('students.show', entry.student.id)} className="hover:text-brand hover:underline">
                                        {entry.student.sortable_name}
                                    </Link>
                                    <StatusBadge status={entry.status} />
                                    <span className="text-muted">
                                        {formatDate(entry.enrolled_at)} to {formatDate(entry.ended_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            <EnrollModal show={enrolling} classId={classId} candidates={candidates} onClose={() => setEnrolling(false)} />

            <ConfirmDialog
                show={withdrawing !== null}
                title={`Withdraw ${withdrawing?.student.full_name}?`}
                message="They leave the class roster. The enrollment stays in their history."
                confirmLabel="Withdraw"
                processing={busy}
                onConfirm={() =>
                    withdrawing &&
                    send('patch', route('enrollments.withdraw', withdrawing.id), {}, { onSuccess: () => setWithdrawing(null) })
                }
                onClose={() => setWithdrawing(null)}
            />
        </Card>
    );
}

/**
 * Pick students to enroll. The list of candidates is fetched from the server
 * (only active students not already in the class), filtered by the search box.
 */
function EnrollModal({
    show,
    classId,
    candidates,
    onClose,
}: {
    show: boolean;
    classId: number;
    candidates?: EnrollmentCandidate[];
    onClose: () => void;
}) {
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{ student_ids: number[] }>({
        student_ids: [],
    });

    const loadCandidates = (term: string) => {
        router.reload({
            only: ['candidates'],
            data: { candidate_search: term },
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Load the first page of candidates when the dialog opens.
    useEffect(() => {
        if (show) {
            loadCandidates('');
        }
    }, [show]);

    // Search a moment after the user stops typing.
    useEffect(() => {
        if (!show) {
            return;
        }
        const timer = setTimeout(() => loadCandidates(search), 300);
        return () => clearTimeout(timer);
    }, [search]);

    const close = () => {
        reset();
        clearErrors();
        setSearch('');
        onClose();
    };

    const toggle = (id: number, checked: boolean) =>
        setData('student_ids', checked ? [...data.student_ids, id] : data.student_ids.filter((value) => value !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('enrollments.store', classId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal show={show} onClose={close} maxWidth="lg">
            <form onSubmit={submit} className="flex max-h-[85vh] flex-col">
                <div className="space-y-3 border-b border-line p-6">
                    <DialogTitle className="font-display text-lg font-semibold">Enroll students</DialogTitle>
                    <p className="text-sm text-muted">Only active students who are not already in this class are listed.</p>
                    <label htmlFor="candidate-search" className="sr-only">
                        Search students
                    </label>
                    <TextInput
                        id="candidate-search"
                        type="search"
                        placeholder="Search by name or student number"
                        className="block w-full"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="min-h-40 flex-1 overflow-y-auto px-6 py-3" aria-busy={loading}>
                    {!candidates || (loading && candidates.length === 0) ? (
                        <p className="py-6 text-center text-sm text-muted">Loading students…</p>
                    ) : candidates.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted">No students to enroll match this search.</p>
                    ) : (
                        <ul className="space-y-1">
                            {candidates.map((candidate) => (
                                <li key={candidate.id}>
                                    <label className="flex min-h-10 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-canvas">
                                        <Checkbox
                                            checked={data.student_ids.includes(candidate.id)}
                                            onChange={(e) => toggle(candidate.id, e.target.checked)}
                                        />
                                        <span className="flex-1 text-sm">{candidate.name}</span>
                                        <span className="font-mono text-xs text-muted">{candidate.student_number}</span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-3 border-t border-line p-6">
                    <InputError message={errors.student_ids} />
                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted" role="status">
                            {data.student_ids.length} selected
                        </p>
                        <div className="flex flex-col-reverse gap-3 sm:flex-row">
                            <Button variant="secondary" onClick={close}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing || data.student_ids.length === 0}>
                                {processing ? 'Enrolling…' : 'Enroll selected'}
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
