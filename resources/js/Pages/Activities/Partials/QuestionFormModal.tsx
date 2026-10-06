import Button from '@/Components/Button';
import Checkbox from '@/Components/Checkbox';
import FormField from '@/Components/FormField';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { Question, QuestionType } from '@/Types/activities';
import { DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface OptionRow {
    text: string;
    is_correct: boolean;
}

const MAX_OPTIONS = 10;

const blankOptions = (): OptionRow[] => [
    { text: '', is_correct: true },
    { text: '', is_correct: false },
];

/** What the teacher fills in under the question for each type. */
const help: Record<QuestionType, string> = {
    multiple_choice: 'Students pick one option. Mark the correct one.',
    multiple_select: 'Students pick every option that applies. Mark all the correct ones.',
    true_false: 'Students answer true or false.',
    identification: 'Students type a word or phrase. List every answer you accept; capitals and extra spaces are ignored.',
    short_answer: 'Students write a sentence or two. You score it yourself.',
    essay: 'Students write a longer answer. You score it yourself.',
};

/**
 * A dialog for adding a question to an activity, or editing `question`.
 * The options editor changes with the question type.
 */
export default function QuestionFormModal({
    show,
    activityId,
    question,
    types,
    onClose,
}: {
    show: boolean;
    activityId: number;
    question: Question | null;
    types: Option[];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        question: '',
        question_type: 'multiple_choice' as QuestionType,
        points: '1',
        options: blankOptions(),
        correct_answer: 'true',
    });

    // Fill the form with the question being edited each time the dialog opens.
    useEffect(() => {
        if (show) {
            clearErrors();
            setData({
                question: question?.question ?? '',
                question_type: question?.type ?? 'multiple_choice',
                points: question ? String(question.points) : '1',
                options:
                    question && question.type !== 'true_false' && question.options.length > 0
                        ? question.options.map((option) => ({ text: option.text, is_correct: option.is_correct ?? false }))
                        : blankOptions(),
                correct_answer: question?.type === 'true_false' && question.options.find((o) => o.is_correct)?.text === 'False' ? 'false' : 'true',
            });
        }
    }, [show, question]);

    const close = () => {
        reset();
        onClose();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: close };

        if (question) {
            put(route('questions.update', question.id), options);
        } else {
            post(route('questions.store', activityId), options);
        }
    };

    const type = data.question_type;
    const usesOptions = type === 'multiple_choice' || type === 'multiple_select' || type === 'identification';

    const updateOption = (index: number, changes: Partial<OptionRow>) =>
        setData(
            'options',
            data.options.map((option, i) => {
                if (i === index) {
                    return { ...option, ...changes };
                }

                // Multiple choice has a single correct option.
                return type === 'multiple_choice' && changes.is_correct ? { ...option, is_correct: false } : option;
            }),
        );

    const optionError =
        errors.options ?? Object.entries(errors).find(([key]) => key.startsWith('options.'))?.[1];

    return (
        <Modal show={show} onClose={close} maxWidth="2xl">
            <form onSubmit={submit} className="space-y-5 p-6">
                <DialogTitle className="font-display text-lg font-semibold">{question ? 'Edit question' : 'Add a question'}</DialogTitle>

                <div className="grid gap-5 sm:grid-cols-3">
                    <FormField id="question_type" label="Type" error={errors.question_type} required className="sm:col-span-2">
                        <SelectInput
                            value={type}
                            options={types}
                            onChange={(e) => setData('question_type', e.target.value as QuestionType)}
                        />
                    </FormField>
                    <FormField id="points" label="Points" error={errors.points} required>
                        <TextInput type="number" min="0" step="0.5" value={data.points} onChange={(e) => setData('points', e.target.value)} />
                    </FormField>
                </div>

                <FormField id="question" label="Question" error={errors.question} hint={help[type]} required>
                    <Textarea rows={3} value={data.question} onChange={(e) => setData('question', e.target.value)} />
                </FormField>

                {type === 'true_false' && (
                    <fieldset>
                        <legend className="text-sm font-medium">Correct answer</legend>
                        <div className="mt-2 flex gap-6">
                            {['true', 'false'].map((value) => (
                                <label key={value} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="correct_answer"
                                        value={value}
                                        checked={data.correct_answer === value}
                                        onChange={() => setData('correct_answer', value)}
                                        className="size-4 border-line text-brand"
                                    />
                                    {value === 'true' ? 'True' : 'False'}
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.correct_answer} className="mt-2" />
                    </fieldset>
                )}

                {usesOptions && (
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">{type === 'identification' ? 'Accepted answers' : 'Options'}</legend>
                        {data.options.map((option, index) => (
                            <div key={index} className="flex items-center gap-3">
                                {type !== 'identification' && (
                                    <label className="flex shrink-0 items-center gap-2 text-sm text-muted">
                                        {type === 'multiple_choice' ? (
                                            <input
                                                type="radio"
                                                name="correct_option"
                                                checked={option.is_correct}
                                                onChange={() => updateOption(index, { is_correct: true })}
                                                className="size-4 border-line text-brand"
                                            />
                                        ) : (
                                            <Checkbox checked={option.is_correct} onChange={(e) => updateOption(index, { is_correct: e.target.checked })} />
                                        )}
                                        <span className="sr-only">Option {index + 1} is correct</span>
                                    </label>
                                )}
                                <TextInput
                                    aria-label={`${type === 'identification' ? 'Answer' : 'Option'} ${index + 1}`}
                                    value={option.text}
                                    onChange={(e) => updateOption(index, { text: e.target.value })}
                                    className="min-w-0 flex-1"
                                />
                                <button
                                    type="button"
                                    onClick={() => setData('options', data.options.filter((_, i) => i !== index))}
                                    disabled={data.options.length <= 1}
                                    className="rounded px-2 py-1 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-40"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                        {data.options.length < MAX_OPTIONS && (
                            <Button
                                small
                                variant="ghost"
                                onClick={() => setData('options', [...data.options, { text: '', is_correct: false }])}
                            >
                                {type === 'identification' ? 'Add another answer' : 'Add an option'}
                            </Button>
                        )}
                        <InputError message={optionError} />
                        {type !== 'identification' && (
                            <p className="text-xs text-muted">
                                {type === 'multiple_choice' ? 'Select the correct option.' : 'Tick every correct option.'}
                            </p>
                        )}
                    </fieldset>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : question ? 'Save question' : 'Add question'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
