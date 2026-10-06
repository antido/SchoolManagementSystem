import Button from '@/Components/Button';
import FormField from '@/Components/FormField';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { cn } from '@/Utils/format';
import { DialogTitle } from '@headlessui/react';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export interface AwardCandidate {
    id: number;
    name: string;
    student_number: string;
    classes: (Option<number> & { academic_year_id: number })[];
}

/**
 * Give an award: search for a student, then say when, and optionally for
 * which year and class.
 */
export default function GrantAwardModal({
    show,
    awardId,
    awardName,
    candidates,
    academicYears,
    activeYearId,
    onClose,
}: {
    show: boolean;
    awardId: number;
    awardName: string;
    candidates?: AwardCandidate[];
    academicYears: Option<number>[];
    activeYearId: number | null;
    onClose: () => void;
}) {
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [chosen, setChosen] = useState<AwardCandidate | null>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        student_id: '' as number | '',
        academic_year_id: (activeYearId ?? '') as number | '',
        school_class_id: '' as number | '',
        awarded_on: new Date().toISOString().slice(0, 10),
        remarks: '',
    });

    // Search a moment after the user stops typing.
    useEffect(() => {
        if (!show || search.trim().length < 2) {
            return;
        }

        const timer = setTimeout(
            () =>
                router.reload({
                    only: ['candidates'],
                    data: { candidate_search: search },
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
                }),
            300,
        );

        return () => clearTimeout(timer);
    }, [search, show]);

    const choose = (candidate: AwardCandidate) => {
        setChosen(candidate);
        const latest = candidate.classes[0];
        setData({
            ...data,
            student_id: candidate.id,
            school_class_id: latest?.value ?? '',
            academic_year_id: latest?.academic_year_id ?? data.academic_year_id,
        });
    };

    const close = () => {
        reset();
        clearErrors();
        setSearch('');
        setChosen(null);
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('awards.grant', awardId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal show={show} onClose={close} maxWidth="lg">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">Give {awardName}</DialogTitle>

                {chosen ? (
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-brand/30 bg-brand-soft px-4 py-3">
                        <div>
                            <p className="font-medium">{chosen.name}</p>
                            <p className="font-mono text-xs text-muted">{chosen.student_number}</p>
                        </div>
                        <Button small variant="ghost" onClick={() => setChosen(null)}>
                            Change
                        </Button>
                    </div>
                ) : (
                    <div>
                        <label htmlFor="grant-search" className="text-sm font-medium">
                            Student
                        </label>
                        <TextInput
                            id="grant-search"
                            type="search"
                            placeholder="Type a name or student number"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="mt-1 w-full"
                            autoComplete="off"
                        />
                        <div className="mt-2 max-h-56 overflow-y-auto rounded-lg border border-line" aria-live="polite">
                            {search.trim().length < 2 ? (
                                <p className="px-3 py-2 text-sm text-muted">Type at least two letters.</p>
                            ) : loading && !candidates?.length ? (
                                <p className="px-3 py-2 text-sm text-muted">Searching…</p>
                            ) : !candidates?.length ? (
                                <p className="px-3 py-2 text-sm text-muted">No students found.</p>
                            ) : (
                                <ul className="divide-y divide-line">
                                    {candidates.map((candidate) => (
                                        <li key={candidate.id}>
                                            <button
                                                type="button"
                                                onClick={() => choose(candidate)}
                                                className={cn('flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-canvas')}
                                            >
                                                <span className="flex-1 text-sm">{candidate.name}</span>
                                                <span className="font-mono text-xs text-muted">{candidate.student_number}</span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <InputError message={errors.student_id} className="mt-1" />
                    </div>
                )}

                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="awarded_on" label="Date" error={errors.awarded_on} required>
                        <TextInput type="date" value={data.awarded_on} onChange={(e) => setData('awarded_on', e.target.value)} />
                    </FormField>
                    <FormField id="academic_year_id" label="Academic year" error={errors.academic_year_id}>
                        <SelectInput
                            value={data.academic_year_id}
                            options={academicYears}
                            placeholder="None"
                            onChange={(e) => setData('academic_year_id', e.target.value ? Number(e.target.value) : '')}
                        />
                    </FormField>
                    {chosen && chosen.classes.length > 0 && (
                        <FormField id="school_class_id" label="Class" error={errors.school_class_id} className="sm:col-span-2">
                            <SelectInput
                                value={data.school_class_id}
                                options={chosen.classes}
                                placeholder="None"
                                onChange={(e) => setData('school_class_id', e.target.value ? Number(e.target.value) : '')}
                            />
                        </FormField>
                    )}
                    <FormField id="remarks" label="Remarks" error={errors.remarks} hint="Shown to the student." className="sm:col-span-2">
                        <Textarea rows={2} value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} />
                    </FormField>
                </div>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing || !chosen}>
                        {processing ? 'Saving…' : 'Give award'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
