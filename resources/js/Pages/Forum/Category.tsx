import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { Head } from '@inertiajs/react';
import TopicList, { TopicSummary } from './Partials/TopicList';

/** The topics of one category, pinned ones first. */
export default function Category({
    category,
    topics,
    can,
}: {
    category: { id: number; name: string; description: string | null };
    topics: Paginator<TopicSummary>;
    can: { create: boolean };
}) {
    const newTopic = can.create && <Button href={route('forum.topics.create', category.id)}>New topic</Button>;

    return (
        <AuthenticatedLayout>
            <Head title={category.name} />
            <PageHeader
                title={category.name}
                intro={category.description ?? undefined}
                breadcrumbs={[{ label: 'Forum', href: route('forum.index') }, { label: category.name }]}
                actions={newTopic}
            />

            <Card flush>
                {topics.data.length === 0 ? (
                    <EmptyState icon="chat" title="No topics yet" message="Start the first discussion." action={newTopic} />
                ) : (
                    <TopicList topics={topics.data} />
                )}
                <Pagination paginator={topics} />
            </Card>
        </AuthenticatedLayout>
    );
}
