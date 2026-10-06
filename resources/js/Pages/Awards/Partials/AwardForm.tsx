import AwardBadge from '@/Components/AwardBadge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { Award } from '@/Types/awards';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** The form for adding and editing an award. Pass `award` when editing. */
export default function AwardForm({ award, statuses }: { award?: Award; statuses: Option[] }) {
    const editing = award !== undefined;

    const { data, setData, transform, post, processing, errors } = useForm({
        name: award?.name ?? '',
        description: award?.description ?? '',
        criteria: award?.criteria ?? '',
        status: award?.status ?? 'active',
        image: null as File | null,
        remove_image: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // An image cannot be sent with PUT, so edits are a POST that Laravel treats as PUT.
        transform((values) => (editing ? { ...values, _method: 'put' } : values));
        post(editing ? route('awards.update', award.id) : route('awards.store'), { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <Card>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField id="name" label="Name" error={errors.name} hint="For example Academic Excellence." required className="sm:col-span-2">
                        <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>
                    <FormField id="description" label="Description" error={errors.description} className="sm:col-span-2">
                        <Textarea rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>
                    <FormField id="criteria" label="Who receives it" error={errors.criteria} hint="The criteria students must meet." className="sm:col-span-2">
                        <Textarea rows={3} value={data.criteria} onChange={(e) => setData('criteria', e.target.value)} />
                    </FormField>
                    <FormField id="status" label="Status" error={errors.status} hint="Inactive awards cannot be given." required>
                        <SelectInput value={data.status} options={statuses} onChange={(e) => setData('status', e.target.value)} />
                    </FormField>
                </div>
            </Card>

            <Card title="Picture">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <AwardBadge imageUrl={data.remove_image ? null : (award?.image_url ?? null)} className="size-20" />
                    <div className="space-y-3">
                        <FormField id="image" label="Upload a picture" error={errors.image} hint="JPG, PNG or WebP, up to 2 MB.">
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink"
                            />
                        </FormField>
                        {award?.image_url && (
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.remove_image} onChange={(e) => setData('remove_image', e.target.checked)} />
                                Remove the current picture
                            </label>
                        )}
                    </div>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>{processing ? 'Saving…' : editing ? 'Save changes' : 'Add award'}</PrimaryButton>
                <Button variant="secondary" href={editing ? route('awards.show', award.id) : route('awards.index')}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
