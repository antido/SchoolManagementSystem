import AttachmentList from '@/Components/AttachmentList';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FileInput from '@/Components/FileInput';
import ConfirmDialog from '@/Components/ConfirmDialog';
import FormField from '@/Components/FormField';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import PrimaryButton from '@/Components/PrimaryButton';
import Textarea from '@/Components/Textarea';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option, Paginator } from '@/Types';
import { Attachment } from '@/Types/activities';
import { cn, firstListError, formatDateTime, humanize } from '@/Utils/format';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { TopicSummary } from './Partials/TopicList';

interface Post {
    id: number;
    body: string | null;
    attachments: Attachment[];
    removed: boolean;
    is_opening: boolean;
    author: { name: string; role: string | null };
    created_at: string;
    edited_at: string | null;
    reactions: Record<string, { count: number; mine: boolean }>;
    reported: boolean;
    can: { update: boolean; delete: boolean; react: boolean; report: boolean };
}

export default function Topic({
    topic,
    posts,
    reactionTypes,
    can,
}: {
    topic: TopicSummary & { category: { id: number; name: string }; deleted: boolean };
    posts: Paginator<Post>;
    reactionTypes: Option[];
    can: { reply: boolean; moderate: boolean; delete: boolean };
}) {
    const [removing, setRemoving] = useState<Post | 'topic' | null>(null);
    const [reporting, setReporting] = useState<Post | null>(null);
    const [reason, setReason] = useState('');
    const { busy, send } = useRequest();
    const { errors } = usePage().props as { errors: Record<string, string> };

    const reply = useForm({ body: '', attachments: [] as File[] });

    const submitReply: FormEventHandler = (e) => {
        e.preventDefault();
        reply.post(route('forum.topics.reply', topic.id), { forceFormData: true, onSuccess: () => reply.reset() });
    };

    return (
        <AuthenticatedLayout>
            <Head title={topic.title} />
            <PageHeader
                title={topic.title}
                intro={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        {topic.deleted && <Badge tone="danger">Removed</Badge>}
                        {topic.is_pinned && <Badge tone="brand">Pinned</Badge>}
                        {topic.is_locked && <Badge>Locked</Badge>}
                        <span>
                            Started by {topic.author} in {topic.category.name}
                        </span>
                    </span>
                }
                breadcrumbs={[
                    { label: 'Forum', href: route('forum.index') },
                    { label: topic.category.name, href: route('forum.categories.show', topic.category.id) },
                    { label: topic.title },
                ]}
                actions={
                    <>
                        {can.moderate && !topic.deleted && (
                            <>
                                <Button variant="secondary" disabled={busy} onClick={() => send('patch', route('forum.topics.update', topic.id), { is_pinned: !topic.is_pinned })}>
                                    {topic.is_pinned ? 'Unpin' : 'Pin'}
                                </Button>
                                <Button variant="secondary" disabled={busy} onClick={() => send('patch', route('forum.topics.update', topic.id), { is_locked: !topic.is_locked })}>
                                    {topic.is_locked ? 'Unlock' : 'Lock'}
                                </Button>
                            </>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setRemoving('topic')}>
                                Remove topic
                            </Button>
                        )}
                    </>
                }
            />

            <ol className="space-y-4">
                {posts.data.map((post) => (
                    <li key={post.id} id={`post-${post.id}`} className="scroll-mt-24">
                        <PostCard
                            post={post}
                            reactionTypes={reactionTypes}
                            busy={busy}
                            onReact={(type) => send('post', route('forum.posts.react', post.id), { type })}
                            onRemove={() => setRemoving(post)}
                            onReport={() => setReporting(post)}
                        />
                    </li>
                ))}
            </ol>

            <Pagination paginator={posts} />

            <div className="mt-6">
                {can.reply ? (
                    <Card title="Reply">
                        <form onSubmit={submitReply} className="space-y-4">
                            {topic.is_locked && <p className="text-sm text-warning">This topic is locked. As a moderator you can still reply.</p>}
                            <FormField id="reply-body" label="Your reply" error={reply.errors.body}>
                                <Textarea rows={5} value={reply.data.body} onChange={(e) => reply.setData('body', e.target.value)} />
                            </FormField>
                            <FormField id="reply-attachments" label="Attach files" error={firstListError(reply.errors, 'attachments')}>
                                <FileInput files={reply.data.attachments} onChange={(files) => reply.setData('attachments', files)} />
                            </FormField>
                            <PrimaryButton disabled={reply.processing || reply.data.body.trim() === ''}>
                                {reply.processing ? 'Posting…' : 'Post reply'}
                            </PrimaryButton>
                        </form>
                    </Card>
                ) : (
                    <p className="rounded-xl border border-line bg-surface px-5 py-4 text-sm text-muted">
                        {topic.deleted ? 'This topic was removed.' : topic.is_locked ? 'This topic is locked, so no more replies can be posted.' : 'You can read this topic but not reply.'}
                    </p>
                )}
            </div>

            <ConfirmDialog
                show={removing !== null}
                title={removing === 'topic' || (removing && removing.is_opening) ? 'Remove the whole topic?' : 'Remove this post?'}
                message="It will be replaced by a note saying it was removed."
                confirmLabel="Remove"
                processing={busy}
                onConfirm={() => {
                    if (removing === 'topic') {
                        send('delete', route('forum.topics.destroy', topic.id));
                    } else if (removing) {
                        send('delete', route('forum.posts.destroy', removing.id), {}, { onSuccess: () => setRemoving(null) });
                    }
                }}
                onClose={() => setRemoving(null)}
            />

            <ConfirmDialog
                show={reporting !== null}
                tone="primary"
                title="Report this post?"
                message={
                    <div className="space-y-2">
                        <p>A moderator will look at it. The author is not told who reported it.</p>
                        <label htmlFor="report-reason" className="block text-sm font-medium text-ink">
                            What is wrong with it?
                        </label>
                        <Textarea id="report-reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} />
                        <InputError message={errors.reason} />
                    </div>
                }
                confirmLabel="Send report"
                processing={busy}
                onConfirm={() =>
                    reporting &&
                    send('post', route('forum.posts.report', reporting.id), { reason }, {
                        onSuccess: () => {
                            setReporting(null);
                            setReason('');
                        },
                    })
                }
                onClose={() => setReporting(null)}
            />
        </AuthenticatedLayout>
    );
}

function PostCard({
    post,
    reactionTypes,
    busy,
    onReact,
    onRemove,
    onReport,
}: {
    post: Post;
    reactionTypes: Option[];
    busy: boolean;
    onReact: (type: string) => void;
    onRemove: () => void;
    onReport: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const edit = useForm({ body: post.body ?? '' });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        edit.put(route('forum.posts.update', post.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    if (post.removed) {
        return <p className="rounded-xl border border-dashed border-line px-5 py-4 text-sm text-muted italic">This post was removed.</p>;
    }

    return (
        <article className={cn('rounded-xl border bg-surface', post.is_opening ? 'border-brand/30' : 'border-line')}>
            <header className="flex items-center gap-3 border-b border-line px-5 py-3">
                <Avatar name={post.author.name} size="sm" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">
                        {post.author.name}
                        {post.author.role && post.author.role !== 'student' && (
                            <Badge tone="brand" className="ml-2">
                                {humanize(post.author.role)}
                            </Badge>
                        )}
                    </p>
                    <p className="text-xs text-muted">
                        {formatDateTime(post.created_at)}
                        {post.edited_at && `, edited ${formatDateTime(post.edited_at)}`}
                    </p>
                </div>
            </header>

            <div className="px-5 py-4">
                {editing ? (
                    <form onSubmit={save} className="space-y-3">
                        <label htmlFor={`edit-${post.id}`} className="sr-only">
                            Edit your post
                        </label>
                        <Textarea id={`edit-${post.id}`} rows={5} value={edit.data.body} onChange={(e) => edit.setData('body', e.target.value)} />
                        <InputError message={edit.errors.body} />
                        <div className="flex gap-2">
                            <Button type="submit" small disabled={edit.processing}>
                                Save
                            </Button>
                            <Button small variant="secondary" onClick={() => setEditing(false)}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        <p className="break-words whitespace-pre-line">{post.body}</p>
                        {post.attachments.length > 0 && (
                            <div className="mt-4">
                                <AttachmentList attachments={post.attachments} />
                            </div>
                        )}
                    </>
                )}
            </div>

            <footer className="flex flex-wrap items-center gap-2 px-5 pb-4">
                {reactionTypes.map((type) => {
                    const reaction = post.reactions[type.value];

                    return (
                        <button
                            key={type.value}
                            type="button"
                            disabled={!post.can.react || busy}
                            aria-pressed={reaction.mine}
                            onClick={() => onReact(type.value)}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-medium disabled:cursor-default',
                                reaction.mine ? 'border-brand bg-brand-soft text-brand' : 'border-line text-muted enabled:hover:bg-canvas',
                            )}
                        >
                            {type.label}
                            {reaction.count > 0 && <span className="ml-1 tabular-nums">{reaction.count}</span>}
                        </button>
                    );
                })}

                <span className="ml-auto flex gap-1">
                    {post.can.update && !editing && (
                        <Button small variant="ghost" onClick={() => setEditing(true)}>
                            Edit
                        </Button>
                    )}
                    {post.can.delete && (
                        <Button small variant="ghost" className="text-danger" onClick={onRemove}>
                            Remove
                        </Button>
                    )}
                    {post.can.report && (
                        <Button small variant="ghost" onClick={onReport}>
                            Report
                        </Button>
                    )}
                    {post.reported && <span className="self-center text-xs text-muted">You reported this post.</span>}
                </span>
            </footer>
        </article>
    );
}
