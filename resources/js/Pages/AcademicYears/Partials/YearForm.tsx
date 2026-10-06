import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { AcademicYear } from '@/Types/academics';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** The form for creating and editing an academic year. */
export default function YearForm({ year }: { year?: AcademicYear }) {
    const editing = year !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        name: year?.name ?? '',
        start_date: year?.start_date ?? '',
        end_date: year?.end_date ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('academic-years.update', year.id));
        } else {
            post(route('academic-years.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <Card>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="name"
                        label="Name"
                        error={errors.name}
                        hint="For example 2026-2027."
                        required
                        className="sm:col-span-2"
                    >
                        <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>
                    <FormField id="start_date" label="First day" error={errors.start_date} required>
                        <TextInput
                            type="date"
                            value={data.start_date}
                            onChange={(e) => setData('start_date', e.target.value)}
                        />
                    </FormField>
                    <FormField id="end_date" label="Last day" error={errors.end_date} required>
                        <TextInput
                            type="date"
                            value={data.end_date}
                            onChange={(e) => setData('end_date', e.target.value)}
                        />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Create academic year'}
                </PrimaryButton>
                <Button
                    variant="secondary"
                    href={editing ? route('academic-years.show', year.id) : route('academic-years.index')}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
