import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import FormField from '@/Components/FormField';
import Icon from '@/Components/Icon';
import Modal from '@/Components/Modal';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import TextInput, { inputClasses } from '@/Components/TextInput';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { formatDateTime } from '@/Utils/format';
import { DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, FormEventHandler, useState } from 'react';
import TopicList, { TopicSummary } from './Partials/TopicList';

interface Category {
    id: number;
    name: string;
    description: string | null;
    order: number;
    topics_count: number;
    latest: { id: number; title: string; last_posted_at: string } | null;
    can_delete: boolean;
}

/** The forum's categories, a search across topics, and category management for administrators. */
export default function Index({
    categories,
    search,
    results,
    pendingReports,
    can,
}: {
    categories: Category[];
    search: string;
    results: Paginator<TopicSummary> | null;
    pendingReports: number | null;
    can: { manageCategories: boolean };
}) {
    const [term, setTerm] = useState(search);
    const [editing, setEditing] = useState<Category | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Category | null>(null);
    const { busy, send } = useRequest();

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('forum.index'), term.trim() ? { search: term.trim() } : {}, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Forum" />
            <PageHeader
                title="Forum"
                intro="Ask questions and talk with your classmates and teachers. Be kind and stay on topic."
                actions={
                    <>
                        {pendingReports !== null && (
                            <Button variant="secondary" href={route('forum.reports.index')}>
                                Reports{pendingReports > 0 && ` (${pendingReports})`}
                            </Button>
                        )}
                        {can.manageCategories && <Button onClick={() => setEditing('new')}>Add category</Button>}
                    </>
                }
            />

            <form role="search" onSubmit={submitSearch} className="mb-6 flex gap-2">
                <label htmlFor="forum-search" className="sr-only">
                    Search the forum
                </label>
                <div className="relative min-w-0 flex-1">
                    <Icon name="search" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                    <input
                        id="forum-search"
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Search topics and posts"
                        className={`${inputClasses} block w-full pl-9`}
                    />
                </div>
                <Button type="submit" variant="secondary">
                    Search
                </Button>
            </form>

            {results ? (
                <Card
                    title={`Results for "${search}"`}
                    actions={
                        <Button small variant="ghost" href={route('forum.index')}>
                            Clear search
                        </Button>
                    }
                    flush
                >
                    {results.data.length === 0 ? (
                        <EmptyState icon="search" title="No topics found" message="Try other words." />
                    ) : (
                        <TopicList topics={results.data} showCategory />
                    )}
                    <Pagination paginator={results} />
                </Card>
            ) : categories.length === 0 ? (
                <Card>
                    <EmptyState icon="chat" title="No categories yet" message="An administrator needs to add the first category." />
                </Card>
            ) : (
                <Card flush>
                    <ul className="divide-y divide-line">
                        {categories.map((category) => (
                            <li key={category.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                                <span className="hidden size-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand sm:flex">
                                    <Icon name="chat" className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <Link href={route('forum.categories.show', category.id)} className="font-display font-semibold hover:text-brand hover:underline">
                                        {category.name}
                                    </Link>
                                    {category.description && <p className="text-sm text-muted">{category.description}</p>}
                                </div>
                                <div className="text-sm text-muted sm:w-64 sm:text-right">
                                    <p>
                                        {category.topics_count} {category.topics_count === 1 ? 'topic' : 'topics'}
                                    </p>
                                    {category.latest && (
                                        <p className="truncate text-xs">
                                            Latest:{' '}
                                            <Link href={route('forum.topics.show', category.latest.id)} className="link">
                                                {category.latest.title}
                                            </Link>{' '}
                                            ({formatDateTime(category.latest.last_posted_at)})
                                        </p>
                                    )}
                                </div>
                                {can.manageCategories && (
                                    <div className="flex gap-1">
                                        <Button small variant="ghost" onClick={() => setEditing(category)}>
                                            Edit<span className="sr-only"> {category.name}</span>
                                        </Button>
                                        {category.can_delete && (
                                            <Button small variant="ghost" className="text-danger" onClick={() => setDeleting(category)}>
                                                Delete<span className="sr-only"> {category.name}</span>
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <CategoryModal category={editing} onClose={() => setEditing(null)} />

            <ConfirmDialog
                show={deleting !== null}
                title={`Delete ${deleting?.name}?`}
                confirmLabel="Delete category"
                processing={busy}
                onConfirm={() => deleting && send('delete', route('forum.categories.destroy', deleting.id), {}, { onSuccess: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </AuthenticatedLayout>
    );
}

function CategoryModal({ category, onClose }: { category: Category | 'new' | null; onClose: () => void }) {
    const existing = category !== 'new' && category !== null ? category : null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: existing?.name ?? '',
        description: existing?.description ?? '',
        order: String(existing?.order ?? 0),
    });

    const [shownFor, setShownFor] = useState<Category | 'new' | null>(null);

    // Refill the form whenever a different category is opened.
    if (category !== shownFor) {
        setShownFor(category);
        clearErrors();
        setData({ name: existing?.name ?? '', description: existing?.description ?? '', order: String(existing?.order ?? 0) });
    }

    const close = () => {
        reset();
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };

        if (existing) {
            put(route('forum.categories.update', existing.id), options);
        } else {
            post(route('forum.categories.store'), options);
        }
    };

    return (
        <Modal show={category !== null} onClose={close} maxWidth="lg">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">{existing ? `Edit ${existing.name}` : 'Add category'}</DialogTitle>
                <FormField id="category-name" label="Name" error={errors.name} required>
                    <TextInput value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </FormField>
                <FormField id="category-description" label="Description" error={errors.description}>
                    <TextInput value={data.description} onChange={(e) => setData('description', e.target.value)} />
                </FormField>
                <FormField id="category-order" label="Position" error={errors.order} hint="Lower numbers are listed first.">
                    <TextInput type="number" min="0" value={data.order} onChange={(e) => setData('order', e.target.value)} />
                </FormField>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : existing ? 'Save category' : 'Add category'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
