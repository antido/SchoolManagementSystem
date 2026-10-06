import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import StatusBadge from '@/Components/StatusBadge';
import { useRequest } from '@/Hooks/useRequest';
import { ActivityDetails, Attempt } from '@/Types/activities';
import { formatDateTime } from '@/Utils/format';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

/**
 * A student's attempts at a quiz, examination or questionnaire, with the
 * button to start (or resume) one.
 */
export default function MyAttemptsCard({
    activity,
    attempts,
    inProgressId,
    questionCount,
    canAttempt,
}: {
    activity: ActivityDetails;
    attempts: Attempt[];
    inProgressId: number | null;
    questionCount: number;
    canAttempt: boolean;
}) {
    const [confirming, setConfirming] = useState(false);
    const { busy, send } = useRequest();

    const left = Math.max(0, activity.max_attempts - attempts.length);
    const timeLimit = activity.duration_minutes ? `${activity.duration_minutes} minutes` : 'no time limit';

    const start = () => send('post', route('attempts.store', activity.id), {}, { onFinish: () => setConfirming(false) });

    return (
        <Card title="Your attempts">
            <div className="space-y-4">
                <p className="text-sm text-muted">
                    {questionCount} {questionCount === 1 ? 'question' : 'questions'}, {timeLimit}.{' '}
                    {activity.max_attempts === 1 ? 'One attempt allowed.' : `${activity.max_attempts} attempts allowed; your best score counts.`}
                </p>

                {attempts.length > 0 && (
                    <ul className="divide-y divide-line rounded-lg border border-line">
                        {attempts.map((attempt) => (
                            <li key={attempt.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                                <Link href={route('attempts.show', attempt.id)} className="font-medium hover:text-brand hover:underline">
                                    Attempt {attempt.attempt_number}
                                </Link>
                                <StatusBadge status={attempt.status} />
                                <span className="text-sm text-muted">
                                    {attempt.submitted_at ? `Handed in ${formatDateTime(attempt.submitted_at)}` : `Started ${formatDateTime(attempt.started_at)}`}
                                    {attempt.auto_submitted && ' when time ran out'}
                                </span>
                                {attempt.score !== null && (
                                    <span className="ml-auto font-display font-semibold tabular-nums">
                                        {attempt.score} / {activity.total_points}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {inProgressId ? (
                    <Button href={route('attempts.show', inProgressId)}>Continue your attempt</Button>
                ) : canAttempt ? (
                    <Button onClick={() => setConfirming(true)}>{attempts.length === 0 ? 'Start' : 'Start another attempt'}</Button>
                ) : (
                    <p className="text-sm text-muted">
                        {left === 0 ? 'You have used all your attempts.' : 'This activity is not open for attempts right now.'}
                    </p>
                )}
            </div>

            <ConfirmDialog
                show={confirming}
                tone="primary"
                title={`Start ${activity.title}?`}
                message={
                    activity.duration_minutes
                        ? `You will have ${activity.duration_minutes} minutes. The timer keeps running if you leave the page, and your answers are handed in when time runs out.`
                        : 'Your answers are saved as you go. Hand them in when you are done.'
                }
                confirmLabel="Start now"
                processing={busy}
                onConfirm={start}
                onClose={() => setConfirming(false)}
            />
        </Card>
    );
}
