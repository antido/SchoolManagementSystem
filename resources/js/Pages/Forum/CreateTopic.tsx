import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FileInput from '@/Components/FileInput';
import FormField from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { firstListError } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function CreateTopic({ category }: { category: { id: number; name: string } }) {
    const { data, setData, post, processing, errors } = useForm({ title: '', body: '', attachments: [] as File[] });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('forum.topics.store', category.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="New topic" />
            <PageHeader
                title="New topic"
                intro={`In ${category.name}`}
                breadcrumbs={[
                    { label: 'Forum', href: route('forum.index') },
                    { label: category.name, href: route('forum.categories.show', category.id) },
                    { label: 'New topic' },
                ]}
            />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <div className="space-y-5">
                        <FormField id="title" label="Title" error={errors.title} hint="A short question or subject." required>
                            <TextInput value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        </FormField>
                        <FormField id="body" label="Message" error={errors.body} required>
                            <Textarea rows={8} value={data.body} onChange={(e) => setData('body', e.target.value)} />
                        </FormField>
                        <FormField id="attachments" label="Attach files" error={firstListError(errors, 'attachments')}>
                            <FileInput files={data.attachments} onChange={(files) => setData('attachments', files)} />
                        </FormField>
                    </div>
                </Card>
                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <PrimaryButton disabled={processing}>{processing ? 'Posting…' : 'Post topic'}</PrimaryButton>
                    <Button variant="secondary" href={route('forum.categories.show', category.id)}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
