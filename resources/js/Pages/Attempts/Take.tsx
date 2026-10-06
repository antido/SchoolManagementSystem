import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { HttpError, sendJson } from '@/Lib/http';
import { ActivitySummary, Attempt, Question } from '@/Types/activities';
import { cn } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Answer {
    selected_options: number[];
    answer_text: string;
}

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

/** Wait this long after the last keystroke before saving a typed answer. */
const TYPING_DELAY = 800;

function formatClock(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const pad = (n: number) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
}

function isAnswered(answer: Answer | undefined): boolean {
    return !!answer && (answer.selected_options.length > 0 || answer.answer_text.trim() !== '');
}

/**
 * The exam screen. Answers are saved to the server as the student works;
 * the server keeps the time and refuses answers once it is up, and this
 * page hands the attempt in when its countdown reaches zero.
 */
export default function Take({
    attempt,
    activity,
    questions,
    answers: savedAnswers,
}: {
    attempt: Attempt & { expires_at: string | null; seconds_remaining: number | null };
    activity: ActivitySummary & { instructions: string | null };
    questions: Question[];
    answers: Record<number, Answer>;
}) {
    const [answers, setAnswers] = useState<Record<number, Answer>>(savedAnswers);
    const [current, setCurrent] = useState(0);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const [confirming, setConfirming] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // Count down from the server's figure, not the device clock, which may be wrong.
    const deadline = useRef(attempt.seconds_remaining === null ? null : Date.now() + attempt.seconds_remaining * 1000);
    const [secondsLeft, setSecondsLeft] = useState(attempt.seconds_remaining);

    // The latest answers, for saves that run after a delay.
    const answersRef = useRef(answers);
    answersRef.current = answers;

    const timers = useRef<Record<number, number>>({});
    const inFlight = useRef<Set<Promise<unknown>>>(new Set());

    const save = useCallback(
        (questionId: number, answer: Answer) => {
            setSaveState('saving');

            const request = sendJson('PUT', route('attempts.answer', attempt.id), {
                question_id: questionId,
                selected_options: answer.selected_options,
                answer_text: answer.answer_text,
            })
                .then(() => setSaveState('saved'))
                .catch((error: unknown) => {
                    // The attempt is over (time up, or handed in elsewhere): show the results.
                    if (error instanceof HttpError && (error.status === 403 || error.body.errors?.attempt)) {
                        router.visit(route('attempts.show', attempt.id));
                        return;
                    }
                    setSaveState('error');
                })
                .finally(() => inFlight.current.delete(request));

            inFlight.current.add(request);
        },
        [attempt.id],
    );

    /** Send every waiting save now and wait for all of them. */
    const flush = useCallback(async () => {
        Object.entries(timers.current).forEach(([questionId, timer]) => {
            window.clearTimeout(timer);
            delete timers.current[Number(questionId)];
            save(Number(questionId), answersRef.current[Number(questionId)]);
        });
        await Promise.allSettled([...inFlight.current]);
    }, [save]);

    const update = (question: Question, changes: Partial<Answer>, delay = 0) => {
        const next: Answer = { ...(answers[question.id] ?? { selected_options: [], answer_text: '' }), ...changes };
        setAnswers({ ...answers, [question.id]: next });
        setSaveState('saving');

        window.clearTimeout(timers.current[question.id]);
        timers.current[question.id] = window.setTimeout(() => {
            delete timers.current[question.id];
            save(question.id, answersRef.current[question.id]);
        }, delay);
    };

    const handIn = useCallback(async () => {
        setSubmitting(true);
        await flush();
        router.post(route('attempts.submit', attempt.id));
    }, [flush, attempt.id]);

    // Tick the clock; hand in when it reaches zero.
    useEffect(() => {
        if (deadline.current === null) {
            return;
        }

        const tick = window.setInterval(() => {
            const left = Math.max(0, Math.round((deadline.current! - Date.now()) / 1000));
            setSecondsLeft(left);

            if (left === 0) {
                window.clearInterval(tick);
                handIn();
            }
        }, 1000);

        return () => window.clearInterval(tick);
    }, [handIn]);

    // Retry a failed save every few seconds.
    useEffect(() => {
        if (saveState !== 'error') {
            return;
        }

        const retry = window.setTimeout(() => {
            const lastAnswered = Object.keys(answersRef.current).map(Number);
            lastAnswered.forEach((id) => save(id, answersRef.current[id]));
        }, 4000);

        return () => window.clearTimeout(retry);
    }, [saveState, save]);

    const question = questions[current];
    const answer = answers[question?.id];
    const unanswered = questions.filter((q) => !isAnswered(answers[q.id])).length;

    const clockTone =
        secondsLeft === null ? '' : secondsLeft <= 60 ? 'bg-danger-soft text-danger' : secondsLeft <= 300 ? 'bg-warning-soft text-warning' : 'bg-brand-soft text-brand';

    return (
        <AuthenticatedLayout>
            <Head title={activity.title} />

            <div className="sticky top-16 z-10 -mx-4 -mt-6 mb-6 flex flex-wrap items-center gap-3 border-b border-line bg-canvas/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mt-8">
                <div className="min-w-0 flex-1">
                    <h1 className="truncate font-display text-lg font-semibold">{activity.title}</h1>
                    <p className="text-xs text-muted" aria-live="polite">
                        {saveState === 'saving' && 'Saving…'}
                        {saveState === 'saved' && 'All answers saved'}
                        {saveState === 'error' && <span className="text-danger">Not saved. Check your connection; retrying…</span>}
                        {saveState === 'idle' && `Attempt ${attempt.attempt_number}`}
                    </p>
                </div>
                {secondsLeft !== null && (
                    <div className={cn('rounded-lg px-3 py-1.5 font-display text-lg font-semibold tabular-nums', clockTone)} role="timer">
                        <span className="sr-only">Time left: </span>
                        {formatClock(secondsLeft)}
                    </div>
                )}
                <Button onClick={() => setConfirming(true)} disabled={submitting}>
                    Hand in
                </Button>
            </div>

            {/* Announce the last minutes to screen readers once, not every second. */}
            <p className="sr-only" aria-live="assertive">
                {secondsLeft === 300 && 'Five minutes left.'}
                {secondsLeft === 60 && 'One minute left.'}
            </p>

            <div className="grid gap-6 lg:grid-cols-[1fr_16rem]">
                <div className="space-y-6">
                    {current === 0 && activity.instructions && (
                        <Card title="Instructions">
                            <p className="whitespace-pre-line">{activity.instructions}</p>
                        </Card>
                    )}

                    {question && (
                        <Card
                            title={`Question ${current + 1} of ${questions.length}`}
                            actions={
                                <span className="text-sm text-muted">
                                    {question.points} {question.points === 1 ? 'point' : 'points'}
                                </span>
                            }
                        >
                            <fieldset>
                                <legend className="mb-4 text-lg font-medium whitespace-pre-line">{question.question}</legend>

                                {(question.type === 'multiple_choice' || question.type === 'true_false') && (
                                    <div className="space-y-2">
                                        {question.options.map((option) => (
                                            <label
                                                key={option.id}
                                                className={cn(
                                                    'flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3',
                                                    answer?.selected_options.includes(option.id) ? 'border-brand bg-brand-soft' : 'border-line hover:bg-canvas',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`question-${question.id}`}
                                                    checked={answer?.selected_options.includes(option.id) ?? false}
                                                    onChange={() => update(question, { selected_options: [option.id] })}
                                                    className="size-4 border-line text-brand"
                                                />
                                                {option.text}
                                            </label>
                                        ))}
                                    </div>
                                )}

                                {question.type === 'multiple_select' && (
                                    <div className="space-y-2">
                                        <p className="text-sm text-muted">Choose every answer that applies.</p>
                                        {question.options.map((option) => {
                                            const chosen = answer?.selected_options.includes(option.id) ?? false;

                                            return (
                                                <label
                                                    key={option.id}
                                                    className={cn(
                                                        'flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3',
                                                        chosen ? 'border-brand bg-brand-soft' : 'border-line hover:bg-canvas',
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={chosen}
                                                        onChange={() =>
                                                            update(question, {
                                                                selected_options: chosen
                                                                    ? answer!.selected_options.filter((id) => id !== option.id)
                                                                    : [...(answer?.selected_options ?? []), option.id],
                                                            })
                                                        }
                                                    />
                                                    {option.text}
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}

                                {question.type === 'identification' && (
                                    <TextInput
                                        aria-label="Your answer"
                                        value={answer?.answer_text ?? ''}
                                        onChange={(e) => update(question, { answer_text: e.target.value }, TYPING_DELAY)}
                                        className="w-full"
                                        autoComplete="off"
                                    />
                                )}

                                {(question.type === 'short_answer' || question.type === 'essay') && (
                                    <Textarea
                                        aria-label="Your answer"
                                        rows={question.type === 'essay' ? 12 : 4}
                                        value={answer?.answer_text ?? ''}
                                        onChange={(e) => update(question, { answer_text: e.target.value }, TYPING_DELAY)}
                                    />
                                )}
                            </fieldset>

                            <div className="mt-6 flex justify-between gap-3">
                                <Button variant="secondary" disabled={current === 0} onClick={() => setCurrent(current - 1)}>
                                    Previous
                                </Button>
                                {current < questions.length - 1 ? (
                                    <Button variant="secondary" onClick={() => setCurrent(current + 1)}>
                                        Next
                                    </Button>
                                ) : (
                                    <Button onClick={() => setConfirming(true)} disabled={submitting}>
                                        Hand in
                                    </Button>
                                )}
                            </div>
                        </Card>
                    )}
                </div>

                <Card title="Questions" className="h-fit lg:sticky lg:top-24">
                    <nav aria-label="Questions">
                        <ol className="grid grid-cols-6 gap-2 lg:grid-cols-5">
                            {questions.map((q, index) => {
                                const done = isAnswered(answers[q.id]);

                                return (
                                    <li key={q.id}>
                                        <button
                                            type="button"
                                            onClick={() => setCurrent(index)}
                                            aria-current={index === current ? 'step' : undefined}
                                            aria-label={`Question ${index + 1}${done ? ', answered' : ''}`}
                                            className={cn(
                                                'flex size-10 items-center justify-center rounded-lg border text-sm font-semibold',
                                                done ? 'border-brand bg-brand text-white' : 'border-line bg-surface',
                                                index === current && 'ring-2 ring-pencil ring-offset-2',
                                            )}
                                        >
                                            {index + 1}
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>
                    <p className="mt-4 text-sm text-muted">
                        {questions.length - unanswered} of {questions.length} answered
                    </p>
                </Card>
            </div>

            <ConfirmDialog
                show={confirming}
                tone="primary"
                title="Hand in your answers?"
                message={
                    unanswered > 0
                        ? `${unanswered} ${unanswered === 1 ? 'question has' : 'questions have'} no answer and will score zero. You cannot change your answers afterwards.`
                        : 'You cannot change your answers afterwards.'
                }
                confirmLabel="Hand in"
                processing={submitting}
                onConfirm={handIn}
                onClose={() => setConfirming(false)}
            />
        </AuthenticatedLayout>
    );
}
