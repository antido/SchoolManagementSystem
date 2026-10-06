import AttachmentList from '@/Components/AttachmentList';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FileInput from '@/Components/FileInput';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import Textarea from '@/Components/Textarea';
import { ActivityDetails, Submission, SubmissionStatus } from '@/Types/activities';
import { firstListError, formatDateTime } from '@/Utils/format';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/**
 * A student's own work on an activity: what they handed in, the grade and
 * feedback, and the form to hand in (or replace) their work.
 */
export default function MyWorkCard({
    activity,
    submission,
    status,
    canSubmit,
}: {
    activity: ActivityDetails;
    submission: Submission | null;
    status: SubmissionStatus;
    canSubmit: boolean;
}) {
    const [editing, setEditing] = useState(submission === null);

    const { data, setData, post, processing, errors, reset } = useForm({
        body: submission?.body ?? '',
        attachments: [] as File[],
        remove_attachments: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('submissions.store', activity.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('attachments', 'remove_attachments');
                setEditing(false);
            },
        });
    };

    const toggleRemove = (id: number) =>
        setData(
            'remove_attachments',
            data.remove_attachments.includes(id) ? data.remove_attachments.filter((other) => other !== id) : [...data.remove_attachments, id],
        );

    const lateWarning = activity.availability === 'past_due' && (
        <p className="rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning">
            The due date has passed. Work you hand in now is marked late.
        </p>
    );

    return (
        <Card title="Your work" actions={<StatusBadge status={status} />}>
            {submission && !editing && (
                <div className="space-y-4">
                    <p className="text-sm text-muted">
                        Handed in {formatDateTime(submission.submitted_at)}
                        {submission.is_late && ', after the due date'}.
                    </p>
                    {submission.body && <p className="whitespace-pre-line">{submission.body}</p>}
                    <AttachmentList attachments={submission.attachments} />

                    {submission.score !== null && (
                        <div className="rounded-lg border border-success/30 bg-success-soft p-4">
                            <p className="font-display text-lg font-semibold text-success">
                                {submission.score} / {activity.total_points}
                            </p>
                            {submission.feedback && <p className="mt-2 whitespace-pre-line">{submission.feedback}</p>}
                        </div>
                    )}

                    {canSubmit && (
                        <Button variant="secondary" onClick={() => setEditing(true)}>
                            Change my work
                        </Button>
                    )}
                </div>
            )}

            {!submission && !canSubmit && (
                <p className="text-muted">
                    {status === 'missing' ? 'Nothing was handed in, and the activity no longer accepts work.' : 'This activity is not accepting work.'}
                </p>
            )}

            {editing && canSubmit && (
                <form onSubmit={submit} className="space-y-5">
                    {lateWarning}
                    <FormField id="body" label="Your answer" error={errors.body}>
                        <Textarea rows={8} value={data.body} onChange={(e) => setData('body', e.target.value)} />
                    </FormField>
                    {submission && (
                        <AttachmentList attachments={submission.attachments} removing={data.remove_attachments} onToggleRemove={toggleRemove} />
                    )}
                    <FormField id="attachments" label="Attach files" error={firstListError(errors, 'attachments')}>
                        <FileInput files={data.attachments} onChange={(files) => setData('attachments', files)} />
                    </FormField>
                    <div className="flex flex-col-reverse gap-3 sm:flex-row">
                        <PrimaryButton disabled={processing}>{processing ? 'Handing in…' : 'Hand in'}</PrimaryButton>
                        {submission && (
                            <Button variant="secondary" onClick={() => setEditing(false)}>
                                Cancel
                            </Button>
                        )}
                    </div>
                </form>
            )}
        </Card>
    );
}
