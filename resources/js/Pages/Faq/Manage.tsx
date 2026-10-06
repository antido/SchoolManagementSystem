import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import TextInput from '@/Components/TextInput';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Category {
    id: number;
    name: string;
    order: number;
    faqs_count: number;
}

interface Entry {
    id: number;
    question: string;
    is_published: boolean;
    faq_category_id: number | null;
    category: string | null;
}

/** Every FAQ entry, published or hidden, grouped by category, with ordering and categories. */
export default function Manage({ categories, faqs }: { categories: Category[]; faqs: Entry[] }) {
    const [deleting, setDeleting] = useState<{ label: string; url: string } | null>(null);
    const { busy, send } = useRequest();

    const groups = [
        ...categories.map((category) => ({ id: category.id as number | null, name: category.name })),
        { id: null, name: 'Other questions' },
    ]
        .map((group) => ({ ...group, entries: faqs.filter((faq) => faq.faq_category_id === group.id) }))
        .filter((group) => group.entries.length > 0);

    return (
        <AuthenticatedLayout>
            <Head title="Manage FAQ" />
            <PageHeader
                title="Manage FAQ"
                intro="Only published questions appear on the FAQ page."
                breadcrumbs={[{ label: 'Help and FAQ', href: route('faqs.index') }, { label: 'Manage' }]}
                actions={<Button href={route('faqs.create')}>Add question</Button>}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {groups.length === 0 ? (
                        <Card>
                            <EmptyState icon="help" title="No questions yet" action={<Button href={route('faqs.create')}>Add question</Button>} />
                        </Card>
                    ) : (
                        groups.map((group) => (
                            <Card key={group.id ?? 'other'} title={group.name} flush>
                                <ol className="divide-y divide-line">
                                    {group.entries.map((faq, index) => (
                                        <li key={faq.id} className="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">{faq.question}</p>
                                                {!faq.is_published && <Badge>Hidden</Badge>}
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                <Button small variant="ghost" disabled={busy || index === 0} onClick={() => send('patch', route('faqs.move', faq.id), { direction: 'up' })}>
                                                    Up<span className="sr-only">: move "{faq.question}" up</span>
                                                </Button>
                                                <Button
                                                    small
                                                    variant="ghost"
                                                    disabled={busy || index === group.entries.length - 1}
                                                    onClick={() => send('patch', route('faqs.move', faq.id), { direction: 'down' })}
                                                >
                                                    Down<span className="sr-only">: move "{faq.question}" down</span>
                                                </Button>
                                                <Button small variant="secondary" disabled={busy} onClick={() => send('patch', route('faqs.publish', faq.id))}>
                                                    {faq.is_published ? 'Hide' : 'Publish'}
                                                </Button>
                                                <Button small variant="ghost" href={route('faqs.edit', faq.id)}>
                                                    Edit
                                                </Button>
                                                <Button
                                                    small
                                                    variant="ghost"
                                                    className="text-danger"
                                                    onClick={() => setDeleting({ label: faq.question, url: route('faqs.destroy', faq.id) })}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </Card>
                        ))
                    )}
                </div>

                <Card title="Categories" className="h-fit" flush>
                    <ul className="divide-y divide-line">
                        {categories.map((category) => (
                            <CategoryRow key={category.id} category={category} onDelete={() => setDeleting({ label: category.name, url: route('faqs.categories.destroy', category.id) })} />
                        ))}
                    </ul>
                    <NewCategory />
                </Card>
            </div>

            <ConfirmDialog
                show={deleting !== null}
                title={`Delete "${deleting?.label}"?`}
                confirmLabel="Delete"
                processing={busy}
                onConfirm={() => deleting && send('delete', deleting.url, {}, { onSuccess: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </AuthenticatedLayout>
    );
}

function CategoryRow({ category, onDelete }: { category: Category; onDelete: () => void }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({ name: category.name, order: String(category.order) });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('faqs.categories.update', category.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    return (
        <li className="px-5 py-3">
            {editing ? (
                <form onSubmit={save} className="space-y-2">
                    <TextInput aria-label="Category name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full" />
                    <TextInput aria-label="Position" type="number" min="0" value={data.order} onChange={(e) => setData('order', e.target.value)} className="w-24" />
                    <InputError message={errors.name ?? errors.order} />
                    <div className="flex gap-2">
                        <Button type="submit" small disabled={processing}>
                            Save
                        </Button>
                        <Button small variant="secondary" onClick={() => setEditing(false)}>
                            Cancel
                        </Button>
                    </div>
                </form>
            ) : (
                <div className="flex items-center gap-2">
                    <span className="flex-1">
                        {category.name}
                        <span className="ml-2 text-xs text-muted">{category.faqs_count}</span>
                    </span>
                    <Button small variant="ghost" onClick={() => setEditing(true)}>
                        Edit<span className="sr-only"> {category.name}</span>
                    </Button>
                    <Button small variant="ghost" className="text-danger" onClick={onDelete}>
                        Delete<span className="sr-only"> {category.name}</span>
                    </Button>
                </div>
            )}
        </li>
    );
}

function NewCategory() {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('faqs.categories.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="border-t border-line p-5">
            <label htmlFor="new-category" className="text-sm font-medium">
                New category
            </label>
            <div className="mt-1 flex gap-2">
                <TextInput id="new-category" value={data.name} onChange={(e) => setData('name', e.target.value)} className="min-w-0 flex-1" />
                <Button type="submit" variant="secondary" disabled={processing || data.name.trim() === ''}>
                    Add
                </Button>
            </div>
            <InputError message={errors.name} className="mt-1" />
        </form>
    );
}
