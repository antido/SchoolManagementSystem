import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { GradingScale } from '@/Types/grades';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface BandRow {
    min_percentage: string;
    label: string;
}

/** Add or edit a grading scale: the passing mark and the descriptor for each range. */
export default function ScaleForm({ scale }: { scale?: GradingScale }) {
    const editing = scale !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        name: scale?.name ?? '',
        passing_percentage: String(scale?.passing_percentage ?? 75),
        is_default: scale?.is_default ?? false,
        bands: (scale?.bands.map((band) => ({ min_percentage: String(band.min_percentage), label: band.label })) ?? [
            { min_percentage: '75', label: 'Passed' },
            { min_percentage: '0', label: 'Failed' },
        ]) as BandRow[],
    });

    const updateBand = (index: number, changes: Partial<BandRow>) =>
        setData(
            'bands',
            data.bands.map((band, i) => (i === index ? { ...band, ...changes } : band)),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('grading.scales.update', scale.id));
        } else {
            post(route('grading.scales.store'));
        }
    };

    const fieldError = (index: number, field: string) => (errors as Record<string, string>)[`bands.${index}.${field}`];
    const title = editing ? `Edit ${scale.name}` : 'Add grading scale';

    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <PageHeader title={title} breadcrumbs={[{ label: 'Grading', href: route('grading.index') }, { label: title }]} />

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <Card>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="name" label="Name" error={errors.name} required className="sm:col-span-2">
                            <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </FormField>
                        <FormField id="passing_percentage" label="Passing percentage" error={errors.passing_percentage} required>
                            <TextInput
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                value={data.passing_percentage}
                                onChange={(e) => setData('passing_percentage', e.target.value)}
                            />
                        </FormField>
                        <label className="flex items-center gap-3 self-end pb-2">
                            <Checkbox checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} />
                            <span>Default scale</span>
                        </label>
                    </div>
                </Card>

                <Card title="Descriptors">
                    <p className="mb-4 text-sm text-muted">Each descriptor applies from its percentage up to the next one. One must start at 0%.</p>
                    <div className="space-y-3">
                        {data.bands.map((band, index) => (
                            <div key={index} className="grid grid-cols-[7rem_1fr_auto] items-start gap-3">
                                <div>
                                    <TextInput
                                        aria-label={`Descriptor ${index + 1} starts at (%)`}
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.5"
                                        value={band.min_percentage}
                                        onChange={(e) => updateBand(index, { min_percentage: e.target.value })}
                                        className="w-full"
                                    />
                                    <InputError message={fieldError(index, 'min_percentage')} className="mt-1" />
                                </div>
                                <div>
                                    <TextInput
                                        aria-label={`Descriptor ${index + 1}`}
                                        value={band.label}
                                        onChange={(e) => updateBand(index, { label: e.target.value })}
                                        className="w-full"
                                    />
                                    <InputError message={fieldError(index, 'label')} className="mt-1" />
                                </div>
                                <button
                                    type="button"
                                    disabled={data.bands.length <= 2}
                                    onClick={() => setData('bands', data.bands.filter((_, i) => i !== index))}
                                    className="rounded px-2 py-2 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-40"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                        <InputError message={errors.bands} />
                        <Button variant="secondary" onClick={() => setData('bands', [...data.bands, { min_percentage: '', label: '' }])}>
                            Add a descriptor
                        </Button>
                    </div>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : editing ? 'Save changes' : 'Add grading scale'}</PrimaryButton>
                    <Button variant="secondary" href={route('grading.index')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
