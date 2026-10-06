import AttachmentList from '@/Components/AttachmentList';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ActivitySummary, Submission } from '@/Types/activities';
import { formatDateTime } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface SubmissionDetails extends Submission {
    student: { id: number; full_name: string; student_number: string };
    grader: string | null;
}

export default function Show({
    submission,
    activity,
    can,
}: {
    submission: SubmissionDetails;
    activity: ActivitySummary;
    can: { grade: boolean };
}) {
    const { data, setData, patch, processing, errors } = useForm({
        score: submission.score !== null ? String(submission.score) : '',
        feedback: submission.feedback ?? '',
    });

    const grade: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('submissions.grade', submission.id), { preserveScroll: true });
    };

    const title = `${submission.student.full_name}'s work`;

    return (
        <AuthenticatedLayout>
            <Head title={title} />

            <PageHeader
                title={title}
                intro={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <StatusBadge status={submission.status} />
                        <span>
                            Handed in {formatDateTime(submission.submitted_at)}
                            {submission.is_late && ', after the due date'}
                        </span>
                    </span>
                }
                breadcrumbs={[
                    { label: 'Activities', href: route('activities.index') },
                    { label: activity.title, href: route('activities.show', activity.id) },
                    { label: submission.student.full_name },
                ]}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card title="Work handed in" className="lg:col-span-2">
                    <div className="space-y-4">
                        {submission.body ? (
                            <p className="whitespace-pre-line">{submission.body}</p>
                        ) : (
                            <p className="text-muted">No written answer.</p>
                        )}
                        <AttachmentList attachments={submission.attachments} />
                    </div>
                </Card>

                <Card title="Grade">
                    {can.grade ? (
                        <form onSubmit={grade} className="space-y-5">
                            <FormField id="score" label={`Score out of ${activity.total_points}`} error={errors.score} required>
                                <TextInput
                                    type="number"
                                    min="0"
                                    max={activity.total_points}
                                    step="0.5"
                                    value={data.score}
                                    onChange={(e) => setData('score', e.target.value)}
                                />
                            </FormField>
                            <FormField id="feedback" label="Feedback for the student" error={errors.feedback}>
                                <Textarea rows={6} value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} />
                            </FormField>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : submission.score !== null ? 'Update grade' : 'Save grade'}
                            </PrimaryButton>
                            {submission.graded_at && (
                                <p className="text-xs text-muted">
                                    Last graded {formatDateTime(submission.graded_at)}
                                    {submission.grader && ` by ${submission.grader}`}.
                                </p>
                            )}
                        </form>
                    ) : submission.score !== null ? (
                        <div className="space-y-2">
                            <p className="font-display text-lg font-semibold">
                                {submission.score} / {activity.total_points}
                            </p>
                            {submission.feedback && <p className="whitespace-pre-line">{submission.feedback}</p>}
                        </div>
                    ) : (
                        <p className="text-muted">Not graded yet.</p>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
