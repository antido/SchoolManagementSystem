import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface FaqEntry {
    id: number;
    question: string;
    answer: string;
    is_published: boolean;
    faq_category_id: number | null;
}

/** Add or edit a question. */
export default function Form({ faq, categories }: { faq?: FaqEntry; categories: Option<number>[] }) {
    const editing = faq !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        faq_category_id: faq?.faq_category_id ? String(faq.faq_category_id) : '',
        question: faq?.question ?? '',
        answer: faq?.answer ?? '',
        is_published: faq?.is_published ?? false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('faqs.update', faq.id));
        } else {
            post(route('faqs.store'));
        }
    };

    const title = editing ? 'Edit question' : 'Add question';

    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <PageHeader
                title={title}
                breadcrumbs={[
                    { label: 'Help and FAQ', href: route('faqs.index') },
                    { label: 'Manage', href: route('faqs.manage') },
                    { label: title },
                ]}
            />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <div className="space-y-5">
                        <FormField id="question" label="Question" error={errors.question} required>
                            <TextInput value={data.question} onChange={(e) => setData('question', e.target.value)} />
                        </FormField>
                        <FormField id="answer" label="Answer" error={errors.answer} required>
                            <Textarea rows={8} value={data.answer} onChange={(e) => setData('answer', e.target.value)} />
                        </FormField>
                        <FormField id="faq_category_id" label="Category" error={errors.faq_category_id}>
                            <SelectInput
                                value={data.faq_category_id}
                                options={categories}
                                placeholder="Other questions"
                                onChange={(e) => setData('faq_category_id', e.target.value)}
                            />
                        </FormField>
                        <label className="flex items-center gap-3">
                            <Checkbox checked={data.is_published} onChange={(e) => setData('is_published', e.target.checked)} />
                            <span>Published (shown on the FAQ page)</span>
                        </label>
                    </div>
                </Card>
                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <PrimaryButton disabled={processing}>{processing ? 'Saving…' : editing ? 'Save changes' : 'Add question'}</PrimaryButton>
                    <Button variant="secondary" href={route('faqs.manage')}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
