import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { Subject } from '@/Types/academics';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** The form for adding and editing a subject. Pass `subject` when editing. */
export default function SubjectForm({ subject, statuses }: { subject?: Subject; statuses: Option[] }) {
    const editing = subject !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        code: subject?.code ?? '',
        name: subject?.name ?? '',
        description: subject?.description ?? '',
        status: subject?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('subjects.update', subject.id));
        } else {
            post(route('subjects.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <Card>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="code" label="Code" error={errors.code} hint="For example MATH7." required>
                        <TextInput value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </FormField>
                    <FormField id="status" label="Status" error={errors.status} hint="Inactive subjects cannot be added to classes." required>
                        <SelectInput value={data.status} options={statuses} onChange={(e) => setData('status', e.target.value)} />
                    </FormField>
                    <FormField id="name" label="Name" error={errors.name} required className="sm:col-span-2">
                        <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>
                    <FormField id="description" label="Description" error={errors.description} className="sm:col-span-2">
                        <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Add subject'}
                </PrimaryButton>
                <Button variant="secondary" href={editing ? route('subjects.show', subject.id) : route('subjects.index')}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
