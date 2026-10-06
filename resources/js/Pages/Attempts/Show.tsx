import Card from '@/Components/Card';
import Icon from '@/Components/Icon';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ActivitySummary, Attempt, Question } from '@/Types/activities';
import { cn, formatDateTime } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Item {
    question: Question;
    answer: {
        id: number;
        selected_options: number[];
        answer_text: string | null;
        is_correct: boolean | null;
        points_awarded: number | null;
        feedback: string | null;
    } | null;
    needs_teacher: boolean;
}

/**
 * An attempt after it was handed in: the student sees their answers and
 * score (and the answer key once the activity is closed); the teacher
 * also sees the key and scores written answers.
 */
export default function Show({
    attempt,
    activity,
    items,
    showAnswers,
    can,
}: {
    attempt: Attempt & { student: { id: number; full_name: string } };
    activity: ActivitySummary;
    items: Item[];
    showAnswers: boolean;
    can: { grade: boolean };
}) {
    // One score and feedback box per written answer that exists.
    const gradable = items.filter((item) => item.needs_teacher && item.answer);

    const { data, setData, patch, processing, errors } = useForm({
        scores: Object.fromEntries(
            gradable.map((item) => [
                item.answer!.id,
                { points: item.answer!.points_awarded === null ? '' : String(item.answer!.points_awarded), feedback: item.answer!.feedback ?? '' },
            ]),
        ) as Record<number, { points: string; feedback: string }>,
    });

    const grade: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('attempts.grade', attempt.id), { preserveScroll: true });
    };

    const setScore = (answerId: number, changes: Partial<{ points: string; feedback: string }>) =>
        setData('scores', { ...data.scores, [answerId]: { ...data.scores[answerId], ...changes } });

    const errorFor = (answerId: number, field: string) => (errors as Record<string, string>)[`scores.${answerId}.${field}`];

    const waiting = attempt.status === 'submitted';

    const body = (
        <ol className="space-y-4">
            {items.map((item, index) => {
                const { question, answer } = item;

                return (
                    <li key={question.id}>
                        <Card
                            title={`Question ${index + 1}`}
                            actions={
                                <span
                                    className={cn(
                                        'text-sm font-semibold tabular-nums',
                                        answer?.is_correct === true && 'text-success',
                                        answer?.is_correct === false && 'text-danger',
                                        answer?.points_awarded === null && 'text-muted',
                                    )}
                                >
                                    {answer?.points_awarded ?? '–'} / {question.points}
                                </span>
                            }
                        >
                            <p className="mb-3 font-medium whitespace-pre-line">{question.question}</p>

                            {question.options.length > 0 && question.type !== 'identification' && (
                                <ul className="space-y-1">
                                    {question.options.map((option) => {
                                        const chosen = answer?.selected_options.includes(option.id) ?? false;

                                        return (
                                            <li
                                                key={option.id}
                                                className={cn(
                                                    'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                                                    chosen ? 'border-brand bg-brand-soft' : 'border-line',
                                                )}
                                            >
                                                <span className="flex-1">{option.text}</span>
                                                {chosen && <span className="text-xs font-semibold text-brand">Chosen</span>}
                                                {showAnswers && option.is_correct && (
                                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-success">
                                                        <Icon name="check" className="size-3.5" /> Correct
                                                    </span>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}

                            {(question.type === 'identification' || item.needs_teacher) && (
                                <div className="rounded-lg border border-line bg-canvas px-3 py-2">
                                    <p className="text-xs text-muted">Answer</p>
                                    <p className="whitespace-pre-line">{answer?.answer_text || <span className="text-muted">No answer</span>}</p>
                                </div>
                            )}

                            {question.type === 'identification' && showAnswers && (
                                <p className="mt-2 text-sm text-success">Accepted: {question.options.map((option) => option.text).join(', ')}</p>
                            )}

                            {item.needs_teacher && answer && can.grade && (
                                <div className="mt-4 grid gap-4 sm:grid-cols-[8rem_1fr]">
                                    <div>
                                        <label htmlFor={`points-${answer.id}`} className="text-sm font-medium">
                                            Points
                                        </label>
                                        <TextInput
                                            id={`points-${answer.id}`}
                                            type="number"
                                            min="0"
                                            max={question.points}
                                            step="0.5"
                                            value={data.scores[answer.id].points}
                                            onChange={(e) => setScore(answer.id, { points: e.target.value })}
                                            className="mt-1 w-full"
                                        />
                                        <InputError message={errorFor(answer.id, 'points')} className="mt-1" />
                                    </div>
                                    <div>
                                        <label htmlFor={`feedback-${answer.id}`} className="text-sm font-medium">
                                            Feedback
                                        </label>
                                        <Textarea
                                            id={`feedback-${answer.id}`}
                                            rows={2}
                                            value={data.scores[answer.id].feedback}
                                            onChange={(e) => setScore(answer.id, { feedback: e.target.value })}
                                            className="mt-1"
                                        />
                                    </div>
                                </div>
                            )}

                            {answer?.feedback && !can.grade && (
                                <p className="mt-3 rounded-lg bg-brand-soft px-3 py-2 text-sm">
                                    <span className="font-semibold">Feedback: </span>
                                    {answer.feedback}
                                </p>
                            )}
                        </Card>
                    </li>
                );
            })}
        </ol>
    );

    return (
        <AuthenticatedLayout>
            <Head title={`${activity.title}, attempt ${attempt.attempt_number}`} />

            <PageHeader
                title={`${activity.title}: attempt ${attempt.attempt_number}`}
                intro={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <StatusBadge status={attempt.status} />
                        <span>
                            {attempt.student.full_name}
                            {attempt.submitted_at && `, handed in ${formatDateTime(attempt.submitted_at)}`}
                            {attempt.auto_submitted && ' when time ran out'}
                        </span>
                    </span>
                }
                breadcrumbs={[
                    { label: 'Activities', href: route('activities.index') },
                    { label: activity.title, href: route('activities.show', activity.id) },
                    { label: `Attempt ${attempt.attempt_number}` },
                ]}
            />

            <div className="mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1 rounded-xl border border-line bg-surface px-5 py-4">
                <p className="font-display text-3xl font-semibold tabular-nums">
                    {attempt.score ?? 0} <span className="text-lg text-muted">/ {activity.total_points}</span>
                </p>
                <p className="text-sm text-muted">
                    {waiting ? 'Some answers are waiting for the teacher, so this score may go up.' : 'Final score.'}
                    {!showAnswers && ' Results per question and the correct answers are shown once the teacher closes the activity.'}
                </p>
            </div>

            {can.grade && gradable.length > 0 ? (
                <form onSubmit={grade} className="space-y-6">
                    {body}
                    <div className="sticky bottom-4 flex justify-end">
                        <PrimaryButton disabled={processing} className="shadow-lg">
                            {processing ? 'Saving…' : 'Save scores'}
                        </PrimaryButton>
                    </div>
                </form>
            ) : (
                body
            )}
        </AuthenticatedLayout>
    );
}
