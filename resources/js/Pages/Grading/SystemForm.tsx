import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { GradingSystem } from '@/Types/grades';
import { cn } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ComponentRow {
    name: string;
    weight: string;
    activity_types: string[];
}

/** Add or edit a grading system: its weighted components and its scale. */
export default function SystemForm({
    system,
    scales,
    activityTypes,
}: {
    system?: GradingSystem;
    scales: Option<number>[];
    activityTypes: Option[];
}) {
    const editing = system !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        name: system?.name ?? '',
        grading_scale_id: String(system?.grading_scale_id ?? scales[0]?.value ?? ''),
        is_default: system?.is_default ?? false,
        components: (system?.components.map((component) => ({
            name: component.name,
            weight: String(component.weight),
            activity_types: component.activity_types,
        })) ?? [{ name: '', weight: '100', activity_types: [] }]) as ComponentRow[],
    });

    const total = data.components.reduce((sum, component) => sum + (Number(component.weight) || 0), 0);

    const updateComponent = (index: number, changes: Partial<ComponentRow>) =>
        setData(
            'components',
            data.components.map((component, i) => (i === index ? { ...component, ...changes } : component)),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('grading.systems.update', system.id));
        } else {
            post(route('grading.systems.store'));
        }
    };

    const fieldError = (index: number, field: string) => (errors as Record<string, string>)[`components.${index}.${field}`];
    const title = editing ? `Edit ${system.name}` : 'Add grading system';

    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <PageHeader title={title} breadcrumbs={[{ label: 'Grading', href: route('grading.index') }, { label: title }]} />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="name" label="Name" error={errors.name} required>
                            <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="grading_scale_id" label="Grading scale" error={errors.grading_scale_id} required>
                            <SelectInput
                                value={data.grading_scale_id}
                                options={scales}
                                onChange={(e) => setData('grading_scale_id', e.target.value)}
                            />
                        </FormField>
                        <label className="flex items-center gap-3 sm:col-span-2">
                            <Checkbox checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} />
                            <span>Use for subjects that have not chosen a grading system</span>
                        </label>
                    </div>
                </Card>

                <Card
                    title="Components"
                    actions={
                        <span className={cn('text-sm font-semibold tabular-nums', total === 100 ? 'text-success' : 'text-danger')}>
                            Total {total}%
                        </span>
                    }
                >
                    <div className="space-y-5">
                        {data.components.map((component, index) => (
                            <fieldset key={index} className="rounded-lg border border-line p-4">
                                <legend className="px-1 text-sm font-medium">Component {index + 1}</legend>
                                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                    <div>
                                        <label htmlFor={`component-name-${index}`} className="text-sm font-medium">
                                            Name
                                        </label>
                                        <TextInput
                                            id={`component-name-${index}`}
                                            value={component.name}
                                            onChange={(e) => updateComponent(index, { name: e.target.value })}
                                            className="mt-1 w-full"
                                        />
                                        <InputError message={fieldError(index, 'name')} className="mt-1" />
                                    </div>
                                    <div>
                                        <label htmlFor={`component-weight-${index}`} className="text-sm font-medium">
                                            Weight (%)
                                        </label>
                                        <TextInput
                                            id={`component-weight-${index}`}
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.5"
                                            value={component.weight}
                                            onChange={(e) => updateComponent(index, { weight: e.target.value })}
                                            className="mt-1 w-full"
                                        />
                                        <InputError message={fieldError(index, 'weight')} className="mt-1" />
                                    </div>
                                </div>
                                <p className="mt-3 text-sm font-medium">Counts these kinds of activity</p>
                                <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                                    {activityTypes.map((type) => (
                                        <label key={type.value} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={component.activity_types.includes(type.value)}
                                                onChange={(e) =>
                                                    updateComponent(index, {
                                                        activity_types: e.target.checked
                                                            ? [...component.activity_types, type.value]
                                                            : component.activity_types.filter((value) => value !== type.value),
                                                    })
                                                }
                                            />
                                            {type.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={fieldError(index, 'activity_types')} className="mt-1" />
                                {data.components.length > 1 && (
                                    <Button
                                        small
                                        variant="ghost"
                                        className="mt-3 text-danger"
                                        onClick={() => setData('components', data.components.filter((_, i) => i !== index))}
                                    >
                                        Remove component
                                    </Button>
                                )}
                            </fieldset>
                        ))}
                        <InputError message={errors.components} />
                        {data.components.length < 10 && (
                            <Button
                                variant="secondary"
                                onClick={() => setData('components', [...data.components, { name: '', weight: '', activity_types: [] }])}
                            >
                                Add a component
                            </Button>
                        )}
                    </div>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : editing ? 'Save changes' : 'Add grading system'}</PrimaryButton>
                    <Button variant="secondary" href={route('grading.index')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
