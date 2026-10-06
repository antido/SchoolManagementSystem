import ActivityTypeIcon from '@/Components/Activities/ActivityTypeIcon';
import DueDate from '@/Components/Activities/DueDate';
import AttachmentList from '@/Components/AttachmentList';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import {
    ActivityDetails,
    Attempt,
    AttemptRosterRow,
    Question,
    Submission,
    SubmissionRosterRow,
    SubmissionStatus,
} from '@/Types/activities';
import { formatDateTime } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AttemptsCard from './Partials/AttemptsCard';
import MyAttemptsCard from './Partials/MyAttemptsCard';
import MyWorkCard from './Partials/MyWorkCard';
import QuestionsCard from './Partials/QuestionsCard';
import SubmissionsCard from './Partials/SubmissionsCard';

type StatusAction = 'published' | 'closed';

/** Button text and confirmation for each status change. */
const statusActions: Record<StatusAction, { label: (current: string) => string; message: string }> = {
    published: {
        label: (current) => (current === 'closed' ? 'Reopen' : 'Publish'),
        message: 'Students in the class will be able to see it from its opening date.',
    },
    closed: {
        label: () => 'Close',
        message: 'Students will still see it, but it will be marked closed.',
    },
};

export default function Show({
    activity,
    mySubmission,
    myStatus,
    roster,
    questions,
    questionTypes = [],
    copySources = [],
    questionsLocked = false,
    canChangeQuestions = false,
    myAttempts,
    attemptInProgress = null,
    questionCount = 0,
    attemptRoster,
    can,
}: {
    activity: ActivityDetails;
    /** Set for students, on activities that take work. */
    mySubmission?: Submission | null;
    myStatus?: SubmissionStatus;
    /** Set for teachers who may see submissions. */
    roster?: SubmissionRosterRow[];
    /** Set for people who manage a question-based activity. */
    questions?: Question[];
    questionTypes?: Option[];
    copySources?: Option<number>[];
    questionsLocked?: boolean;
    canChangeQuestions?: boolean;
    /** Set for students, on quizzes, examinations and questionnaires. */
    myAttempts?: Attempt[];
    attemptInProgress?: number | null;
    questionCount?: number;
    /** Set for teachers who may see results. */
    attemptRoster?: AttemptRosterRow[];
    can: { submit: boolean; attempt: boolean; update: boolean; delete: boolean; statuses: StatusAction[] };
}) {
    const [confirming, setConfirming] = useState<StatusAction | 'delete' | null>(null);
    const { busy, send } = useRequest();

    const close = () => setConfirming(null);
    const classSubject = activity.class_subject;

    return (
        <AuthenticatedLayout>
            <Head title={activity.title} />

            <PageHeader
                title={activity.title}
                intro={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <StatusBadge status={activity.availability} />
                        <span>
                            {activity.type_label} for {classSubject.subject}, {classSubject.school_class.name}
                        </span>
                    </span>
                }
                breadcrumbs={[{ label: 'Activities', href: route('activities.index') }, { label: activity.title }]}
                actions={
                    <>
                        {can.statuses.map((status) => (
                            <Button key={status} variant={status === 'published' ? 'primary' : 'secondary'} onClick={() => setConfirming(status)}>
                                {statusActions[status].label(activity.status)}
                            </Button>
                        ))}
                        {can.update && (
                            <Button variant="secondary" href={route('activities.edit', activity.id)}>
                                Edit
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setConfirming('delete')}>
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Instructions">
                        {activity.description && <p className="mb-4 text-muted">{activity.description}</p>}
                        {activity.instructions ? (
                            <p className="whitespace-pre-line">{activity.instructions}</p>
                        ) : (
                            <p className="text-muted">No instructions were given.</p>
                        )}
                        {activity.attachments.length > 0 && (
                            <div className="mt-5">
                                <h3 className="mb-2 text-sm font-semibold">Files</h3>
                                <AttachmentList attachments={activity.attachments} />
                            </div>
                        )}
                    </Card>

                    {myStatus && (
                        <MyWorkCard activity={activity} submission={mySubmission ?? null} status={myStatus} canSubmit={can.submit} />
                    )}

                    {questions && (
                        <QuestionsCard
                            activityId={activity.id}
                            questions={questions}
                            types={questionTypes}
                            copySources={copySources}
                            canChange={canChangeQuestions}
                            locked={questionsLocked}
                        />
                    )}

                    {myAttempts && (
                        <MyAttemptsCard
                            activity={activity}
                            attempts={myAttempts}
                            inProgressId={attemptInProgress}
                            questionCount={questionCount}
                            canAttempt={can.attempt}
                        />
                    )}

                    {attemptRoster && <AttemptsCard roster={attemptRoster} totalPoints={activity.total_points} />}

                    {roster && <SubmissionsCard roster={roster} totalPoints={activity.total_points} />}
                </div>

                <Card>
                    <div className="mb-5 flex items-center gap-3">
                        <ActivityTypeIcon type={activity.type} />
                        <div className="min-w-0">
                            <p className="font-display font-semibold">{activity.type_label}</p>
                            <DueDate activity={activity} className="text-sm" />
                        </div>
                    </div>
                    <DetailList
                        items={[
                            {
                                label: 'Subject',
                                value: (
                                    <Link href={route('class-subjects.show', classSubject.id)} className="link">
                                        {classSubject.subject}
                                    </Link>
                                ),
                            },
                            {
                                label: 'Class',
                                value: (
                                    <Link href={route('classes.show', classSubject.school_class.id)} className="link">
                                        {classSubject.school_class.name}
                                    </Link>
                                ),
                            },
                            { label: 'Term', value: classSubject.term },
                            { label: 'Total points', value: activity.total_points },
                            ...(activity.accepts_submissions
                                ? [{ label: 'Late work', value: activity.allow_late_submissions ? 'Accepted, marked late' : 'Not accepted' }]
                                : []),
                            { label: 'Opens', value: activity.start_at ? formatDateTime(activity.start_at) : 'When published' },
                            { label: 'Due', value: activity.due_at ? formatDateTime(activity.due_at) : 'No due date' },
                            ...(activity.duration_minutes ? [{ label: 'Time limit', value: `${activity.duration_minutes} minutes` }] : []),
                            ...(activity.teacher ? [{ label: 'Teacher', value: activity.teacher.name }] : []),
                        ]}
                    />
                </Card>
            </div>

            {(confirming === 'published' || confirming === 'closed') && (
                <ConfirmDialog
                    show
                    tone="primary"
                    title={`${statusActions[confirming].label(activity.status)} ${activity.title}?`}
                    message={statusActions[confirming].message}
                    confirmLabel={statusActions[confirming].label(activity.status)}
                    processing={busy}
                    onConfirm={() => send('patch', route('activities.status', activity.id), { status: confirming }, { onSuccess: close })}
                    onClose={close}
                />
            )}

            <ConfirmDialog
                show={confirming === 'delete'}
                title={`Delete ${activity.title}?`}
                message="This cannot be undone."
                confirmLabel="Delete activity"
                processing={busy}
                onConfirm={() => send('delete', route('activities.destroy', activity.id), {}, { onSuccess: close })}
                onClose={close}
            />
        </AuthenticatedLayout>
    );
}
