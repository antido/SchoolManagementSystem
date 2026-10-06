import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import Icon from '@/Components/Icon';
import PageHeader from '@/Components/PageHeader';
import { inputClasses } from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/Types';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

interface Group {
    name: string | null;
    faqs: { id: number; question: string; answer: string }[];
}

/**
 * The FAQ: published questions grouped by category, each opening to show
 * its answer. Guests see it in the public frame when the FAQ is public.
 */
export default function Index({ groups, search, can }: { groups: Group[]; search: string; can: { manage: boolean } }) {
    const { auth } = usePage<PageProps>().props;
    const [term, setTerm] = useState(search);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('faqs.index'), term.trim() ? { search: term.trim() } : {}, { preserveState: true });
    };

    const content = (
        <>
            <Head title="Help and FAQ" />
            <PageHeader
                title="Help and FAQ"
                intro="Answers to common questions about the school system."
                actions={can.manage && <Button href={route('faqs.manage')}>Manage FAQ</Button>}
            />

            <form role="search" onSubmit={submit} className="mb-6 flex max-w-2xl gap-2">
                <label htmlFor="faq-search" className="sr-only">
                    Search the FAQ
                </label>
                <div className="relative min-w-0 flex-1">
                    <Icon name="search" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                    <input
                        id="faq-search"
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Search questions and answers"
                        className={`${inputClasses} block w-full pl-9`}
                    />
                </div>
                <Button type="submit" variant="secondary">
                    Search
                </Button>
            </form>

            {groups.length === 0 ? (
                <Card className="max-w-3xl">
                    <EmptyState
                        icon="help"
                        title={search ? 'No questions match your search' : 'No questions yet'}
                        message={search ? 'Try other words, or ask in the forum.' : 'Check back soon.'}
                        action={
                            search && (
                                <Button variant="secondary" href={route('faqs.index')}>
                                    Show all questions
                                </Button>
                            )
                        }
                    />
                </Card>
            ) : (
                <div className="max-w-3xl space-y-8">
                    {groups.map((group, index) => (
                        <section key={group.name ?? index} aria-labelledby={`faq-group-${index}`}>
                            {group.name && (
                                <h2 id={`faq-group-${index}`} className="mb-3 font-display text-lg font-semibold">
                                    {group.name}
                                </h2>
                            )}
                            <div className="divide-y divide-line rounded-xl border border-line bg-surface">
                                {group.faqs.map((faq) => (
                                    <details key={faq.id} className="group" open={search !== ''}>
                                        <summary className="flex cursor-pointer list-none items-center gap-3 px-5 py-4 font-medium hover:bg-canvas/60 [&::-webkit-details-marker]:hidden">
                                            <span className="flex-1">{faq.question}</span>
                                            <Icon name="chevronRight" className="size-4 shrink-0 text-muted transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                                        </summary>
                                        <p className="px-5 pb-4 whitespace-pre-line text-muted">{faq.answer}</p>
                                    </details>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </>
    );

    return auth.user ? <AuthenticatedLayout>{content}</AuthenticatedLayout> : <PublicLayout>{content as ReactNode}</PublicLayout>;
}
