import Button from '@/Components/Button';
import FormField from '@/Components/FormField';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import { AcademicTerm, AcademicYear } from '@/Types/academics';
import { DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

/**
 * A dialog for adding a term to a year, or editing a term when `term` is given.
 */
export default function TermFormModal({
    show,
    year,
    term,
    onClose,
}: {
    show: boolean;
    year: AcademicYear;
    term: AcademicTerm | null;
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        start_date: '',
        end_date: '',
    });

    // Fill the form with the term being edited each time the dialog opens.
    useEffect(() => {
        if (show) {
            clearErrors();
            setData({
                name: term?.name ?? '',
                start_date: term?.start_date ?? year.start_date,
                end_date: term?.end_date ?? '',
            });
        }
    }, [show, term]);

    const close = () => {
        reset();
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: close };

        if (term) {
            put(route('academic-terms.update', term.id), options);
        } else {
            post(route('academic-terms.store', year.id), options);
        }
    };

    return (
        <Modal show={show} onClose={close} maxWidth="lg">
            <form onSubmit={submit} className="space-y-5 p-6">
                <div>
                    <DialogTitle className="font-display text-lg font-semibold">
                        {term ? `Edit ${term.name}` : `Add a term to ${year.name}`}
                    </DialogTitle>
                    <p className="mt-1 text-sm text-muted">
                        Terms must fall inside the year and may not overlap each other.
                    </p>
                </div>

                <FormField id="term-name" label="Name" error={errors.name} hint="For example First Semester." required>
                    <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </FormField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="term-start" label="First day" error={errors.start_date} required>
                        <TextInput
                            type="date"
                            min={year.start_date}
                            max={year.end_date}
                            value={data.start_date}
                            onChange={(e) => setData('start_date', e.target.value)}
                        />
                    </FormField>
                    <FormField id="term-end" label="Last day" error={errors.end_date} required>
                        <TextInput
                            type="date"
                            min={year.start_date}
                            max={year.end_date}
                            value={data.end_date}
                            onChange={(e) => setData('end_date', e.target.value)}
                        />
                    </FormField>
                </div>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : term ? 'Save term' : 'Add term'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
