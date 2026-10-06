import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { usePermissions } from '@/Hooks/usePermissions';
import { Option } from '@/Types';
import { SchoolClassDetails } from '@/Types/academics';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** The form for creating and editing a class. Pass `schoolClass` when editing. */
export default function ClassForm({
    schoolClass,
    years,
    statuses,
    defaultYearId,
}: {
    schoolClass?: SchoolClassDetails;
    years: Option<number>[];
    statuses: Option[];
    defaultYearId?: number | null;
}) {
    const editing = schoolClass !== undefined;
    const { can } = usePermissions();

    const { data, setData, post, put, processing, errors } = useForm({
        academic_year_id: String(schoolClass?.academic_year_id ?? defaultYearId ?? ''),
        name: schoolClass?.name ?? '',
        code: schoolClass?.code ?? '',
        grade_level: schoolClass?.grade_level ?? '',
        section: schoolClass?.section ?? '',
        description: schoolClass?.description ?? '',
        status: schoolClass?.status ?? 'active',
    });

    if (years.length === 0) {
        return (
            <Card>
                <EmptyState
                    icon="calendar"
                    title="Create an academic year first"
                    message="Every class belongs to an academic year."
                    action={
                        can('academic_years.create') && (
                            <Button href={route('academic-years.create')}>New academic year</Button>
                        )
                    }
                />
            </Card>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('classes.update', schoolClass.id));
        } else {
            post(route('classes.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Card>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="academic_year_id" label="Academic year" error={errors.academic_year_id} required>
                        <SelectInput
                            value={data.academic_year_id}
                            options={years}
                            placeholder="Choose a year"
                            onChange={(e) => setData('academic_year_id', e.target.value)}
                        />
                    </FormField>
                    <FormField id="status" label="Status" error={errors.status} required>
                        <SelectInput
                            value={data.status}
                            options={statuses}
                            onChange={(e) => setData('status', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="name"
                        label="Class name"
                        error={errors.name}
                        hint="For example Grade 7 - Sampaguita."
                        required
                        className="sm:col-span-2"
                    >
                        <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>
                    <FormField id="code" label="Code" error={errors.code} hint="Unique within the year, for example G7-SAMP." required>
                        <TextInput value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </FormField>
                    <FormField id="grade_level" label="Grade level" error={errors.grade_level} hint="For example Grade 7." required>
                        <TextInput value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} />
                    </FormField>
                    <FormField id="section" label="Section" error={errors.section}>
                        <TextInput value={data.section} onChange={(e) => setData('section', e.target.value)} />
                    </FormField>
                    <FormField id="description" label="Description" error={errors.description} className="sm:col-span-2">
                        <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Create class'}
                </PrimaryButton>
                <Button
                    variant="secondary"
                    href={editing ? route('classes.show', schoolClass.id) : route('classes.index')}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
