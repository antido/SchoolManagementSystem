import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import Icon from '@/Components/Icon';
import SelectInput from '@/Components/SelectInput';
import { useRequest } from '@/Hooks/useRequest';
import { Option } from '@/Types';
import { Question } from '@/Types/activities';
import { cn } from '@/Utils/format';
import { useState } from 'react';
import QuestionFormModal from './QuestionFormModal';

/**
 * The questions of a quiz, examination or questionnaire with their answer
 * keys, for the teacher: add, edit, reorder, remove and copy questions.
 */
export default function QuestionsCard({
    activityId,
    questions,
    types,
    copySources,
    canChange,
    locked,
}: {
    activityId: number;
    questions: Question[];
    types: Option[];
    copySources: Option<number>[];
    canChange: boolean;
    locked: boolean;
}) {
    const [editing, setEditing] = useState<Question | null>(null);
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState<Question | null>(null);
    const [copyFrom, setCopyFrom] = useState('');
    const { busy, send } = useRequest();

    const total = questions.reduce((sum, question) => sum + question.points, 0);

    const actions = canChange && (
        <Button small onClick={() => setAdding(true)}>
            <Icon name="plus" className="size-4" />
            Add question
        </Button>
    );

    return (
        <Card
            title={`Questions (${questions.length})`}
            actions={
                <>
                    <span className="self-center text-sm text-muted">{total} points</span>
                    {actions}
                </>
            }
            flush
        >
            {locked && (
                <p className="border-b border-line bg-warning-soft px-5 py-3 text-sm text-warning">
                    Students have started answering, so the questions can no longer change.
                </p>
            )}

            {questions.length === 0 ? (
                <EmptyState icon="help" title="No questions yet" message="Add questions one by one, or copy them from another activity." />
            ) : (
                <ol className="divide-y divide-line">
                    {questions.map((question, index) => (
                        <li key={question.id} className="px-5 py-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                                <span className="font-display text-sm font-semibold text-muted">{index + 1}.</span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium whitespace-pre-line">{question.question}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                                        <Badge>{question.type_label}</Badge>
                                        <span>
                                            {question.points} {question.points === 1 ? 'point' : 'points'}
                                        </span>
                                    </div>
                                    {question.options.length > 0 && (
                                        <ul className="mt-3 space-y-1 text-sm">
                                            {question.options.map((option) => (
                                                <li key={option.id} className={cn('flex items-center gap-2', option.is_correct ? 'font-medium text-success' : 'text-muted')}>
                                                    <Icon name={option.is_correct ? 'check' : 'close'} className="size-4 shrink-0" />
                                                    <span className="sr-only">{option.is_correct ? 'Correct:' : 'Incorrect:'}</span>
                                                    {option.text}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                                {canChange && (
                                    <div className="flex shrink-0 flex-wrap gap-1">
                                        <Button
                                            small
                                            variant="ghost"
                                            disabled={busy || index === 0}
                                            onClick={() => send('patch', route('questions.move', question.id), { direction: 'up' })}
                                        >
                                            Up<span className="sr-only">: move question {index + 1} up</span>
                                        </Button>
                                        <Button
                                            small
                                            variant="ghost"
                                            disabled={busy || index === questions.length - 1}
                                            onClick={() => send('patch', route('questions.move', question.id), { direction: 'down' })}
                                        >
                                            Down<span className="sr-only">: move question {index + 1} down</span>
                                        </Button>
                                        <Button small variant="secondary" onClick={() => setEditing(question)}>
                                            Edit<span className="sr-only"> question {index + 1}</span>
                                        </Button>
                                        <Button small variant="ghost" className="text-danger" onClick={() => setRemoving(question)}>
                                            Remove<span className="sr-only"> question {index + 1}</span>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            )}

            {canChange && copySources.length > 0 && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        send('post', route('questions.copy', activityId), { source_activity_id: copyFrom }, { onSuccess: () => setCopyFrom('') });
                    }}
                    className="flex flex-col gap-3 border-t border-line p-5 sm:flex-row sm:items-end"
                >
                    <div className="min-w-0 flex-1">
                        <label htmlFor="copy-from" className="text-sm font-medium">
                            Copy questions from another activity
                        </label>
                        <SelectInput
                            id="copy-from"
                            value={copyFrom}
                            options={copySources}
                            placeholder="Choose an activity"
                            onChange={(e) => setCopyFrom(e.target.value)}
                            className="mt-1 w-full"
                        />
                    </div>
                    <Button type="submit" variant="secondary" disabled={busy || copyFrom === ''}>
                        Copy questions
                    </Button>
                </form>
            )}

            <QuestionFormModal
                show={adding || editing !== null}
                activityId={activityId}
                question={editing}
                types={types}
                onClose={() => {
                    setAdding(false);
                    setEditing(null);
                }}
            />

            <ConfirmDialog
                show={removing !== null}
                title="Remove this question?"
                message={removing?.question}
                confirmLabel="Remove question"
                processing={busy}
                onConfirm={() => removing && send('delete', route('questions.destroy', removing.id), {}, { onSuccess: () => setRemoving(null) })}
                onClose={() => setRemoving(null)}
            />
        </Card>
    );
}
