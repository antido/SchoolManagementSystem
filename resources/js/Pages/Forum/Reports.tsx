import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { cn, formatDateTime } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

interface Report {
    id: number;
    reason: string;
    status: string;
    reported_at: string;
    reporter: string;
    resolver: string | null;
    resolved_at: string | null;
    post: { id: number; body: string; removed: boolean; author: string; topic: { id: number; title: string } };
}

/** The moderators' queue of reported posts. */
export default function Reports({ status, reports }: { status: string; reports: Paginator<Report> }) {
    const { busy, send } = useRequest();

    const tab = (value: string, label: string) => (
        <Link
            href={route('forum.reports.index', { status: value })}
            aria-current={status === value ? 'page' : undefined}
            className={cn('rounded-lg px-3 py-1.5 text-sm font-medium', status === value ? 'bg-brand text-white' : 'text-muted hover:bg-canvas hover:text-ink')}
        >
            {label}
        </Link>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Forum reports" />
            <PageHeader
                title="Forum reports"
                intro="Remove posts that break the rules, or dismiss reports about posts that are fine."
                breadcrumbs={[{ label: 'Forum', href: route('forum.index') }, { label: 'Reports' }]}
            />

            <Card flush>
                <nav aria-label="Report status" className="flex gap-2 border-b border-line p-3">
                    {tab('pending', 'Waiting')}
                    {tab('resolved', 'Removed')}
                    {tab('dismissed', 'Dismissed')}
                </nav>

                {reports.data.length === 0 ? (
                    <EmptyState icon="shield" title={status === 'pending' ? 'No reports waiting' : 'Nothing here'} />
                ) : (
                    <ul className="divide-y divide-line">
                        {reports.data.map((report) => (
                            <li key={report.id} className="space-y-3 px-5 py-4">
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <StatusBadge status={report.status} />
                                    <span className="text-muted">
                                        Reported by {report.reporter}, {formatDateTime(report.reported_at)}
                                    </span>
                                </div>
                                <p className="font-medium">"{report.reason}"</p>
                                <blockquote className="rounded-lg border-l-4 border-line bg-canvas px-4 py-3 text-sm">
                                    <p className="mb-1 text-xs text-muted">
                                        {report.post.author} in{' '}
                                        <Link href={`${route('forum.topics.show', report.post.topic.id)}#post-${report.post.id}`} className="link">
                                            {report.post.topic.title}
                                        </Link>
                                        {report.post.removed && ' (removed)'}
                                    </p>
                                    <p className="break-words whitespace-pre-line">{report.post.body}</p>
                                </blockquote>
                                {report.status === 'pending' ? (
                                    <div className="flex gap-2">
                                        <Button small variant="danger" disabled={busy} onClick={() => send('patch', route('forum.reports.resolve', report.id), { action: 'remove' })}>
                                            Remove post
                                        </Button>
                                        <Button small variant="secondary" disabled={busy} onClick={() => send('patch', route('forum.reports.resolve', report.id), { action: 'dismiss' })}>
                                            Dismiss report
                                        </Button>
                                    </div>
                                ) : (
                                    report.resolver && (
                                        <p className="text-xs text-muted">
                                            Handled by {report.resolver}, {formatDateTime(report.resolved_at)}
                                        </p>
                                    )
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination paginator={reports} />
            </Card>
        </AuthenticatedLayout>
    );
}
