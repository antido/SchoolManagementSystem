import Badge from '@/Components/Badge';
import { formatDateTime } from '@/Utils/format';
import { Link } from '@inertiajs/react';

export interface TopicSummary {
    id: number;
    title: string;
    author: string;
    is_pinned: boolean;
    is_locked: boolean;
    replies_count: number;
    last_posted_at: string | null;
    category: { id: number; name: string } | null;
}

/** Topics as rows: pinned and locked marks, author, replies and last activity. */
export default function TopicList({ topics, showCategory = false }: { topics: TopicSummary[]; showCategory?: boolean }) {
    return (
        <ul className="divide-y divide-line">
            {topics.map((topic) => (
                <li key={topic.id} className="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:gap-4">
                    <div className="min-w-0 flex-1">
                        <p className="flex flex-wrap items-center gap-2">
                            {topic.is_pinned && <Badge tone="brand">Pinned</Badge>}
                            {topic.is_locked && <Badge>Locked</Badge>}
                            <Link href={route('forum.topics.show', topic.id)} className="font-semibold hover:text-brand hover:underline">
                                {topic.title}
                            </Link>
                        </p>
                        <p className="text-xs text-muted">
                            Started by {topic.author}
                            {showCategory && topic.category && ` in ${topic.category.name}`}
                        </p>
                    </div>
                    <p className="text-xs text-muted sm:w-48 sm:text-right">
                        {topic.replies_count} {topic.replies_count === 1 ? 'reply' : 'replies'}
                        {topic.last_posted_at && (
                            <>
                                <span className="hidden sm:inline">
                                    <br />
                                </span>
                                <span className="sm:hidden">, </span>
                                last {formatDateTime(topic.last_posted_at)}
                            </>
                        )}
                    </p>
                </li>
            ))}
        </ul>
    );
}
