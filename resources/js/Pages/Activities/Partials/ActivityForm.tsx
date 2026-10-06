import AttachmentList from '@/Components/AttachmentList';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import FileInput from '@/Components/FileInput';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { ActivityDetails } from '@/Types/activities';
import { firstListError, fromDateTimeInput, toDateTimeInput } from '@/Utils/format';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * The form for creating and editing an activity. Pass `activity` when
 * editing; pass `classSubjects` when creating.
 */
export default function ActivityForm({
    activity,
    classSubjects = [],
    types,
    defaults = {},
}: {
    activity?: ActivityDetails;
    classSubjects?: Option<number>[];
    types: Option[];
    defaults?: { class_subject_id?: number | null; type?: string | null };
}) {
    const editing = activity !== undefined;

    const { data, setData, transform, post, processing, errors } = useForm({
        class_subject_id: String(defaults.class_subject_id ?? ''),
        type: activity?.type ?? defaults.type ?? types[0]?.value ?? '',
        title: activity?.title ?? '',
        description: activity?.description ?? '',
        instructions: activity?.instructions ?? '',
        start_at: toDateTimeInput(activity?.start_at),
        due_at: toDateTimeInput(activity?.due_at),
        duration_minutes: activity?.duration_minutes ? String(activity.duration_minutes) : '',
        total_points: activity ? String(activity.total_points) : '100',
        allow_late_submissions: activity?.allow_late_submissions ?? true,
        max_attempts: activity ? String(activity.max_attempts) : '1',
        shuffle_questions: activity?.shuffle_questions ?? false,
        shuffle_options: activity?.shuffle_options ?? false,
        attachments: [] as File[],
        remove_attachments: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Send dates with the browser's time zone so the server stores the intended moment.
        transform(({ class_subject_id, ...values }) => ({
            // Files cannot be sent with PUT, so edits are a POST that Laravel treats as PUT.
            ...(editing ? { _method: 'put' } : { class_subject_id }),
            ...values,
            start_at: fromDateTimeInput(values.start_at),
            due_at: fromDateTimeInput(values.due_at),
        }));

        post(editing ? route('activities.update', activity.id) : route('activities.store'), { forceFormData: true });
    };

    const takesWork = data.type === 'assignment' || data.type === 'activity';
    const usesQuestions = !takesWork;

    const toggleRemove = (id: number) =>
        setData(
            'remove_attachments',
            data.remove_attachments.includes(id) ? data.remove_attachments.filter((other) => other !== id) : [...data.remove_attachments, id],
        );

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Card title="About the activity">
                <div className="grid gap-5 sm:grid-cols-2">
                    {!editing && (
                        <FormField
                            id="class_subject_id"
                            label="Class subject"
                            error={errors.class_subject_id}
                            required
                            className="sm:col-span-2"
                        >
                            <SelectInput
                                value={data.class_subject_id}
                                options={classSubjects}
                                placeholder="Choose a subject and class"
                                onChange={(e) => setData('class_subject_id', e.target.value)}
                            />
                        </FormField>
                    )}
                    <FormField id="type" label="Kind" error={errors.type} required>
                        <SelectInput value={data.type} options={types} onChange={(e) => setData('type', e.target.value)} />
                    </FormField>
                    {usesQuestions ? (
                        <div>
                            <p className="text-sm font-medium">Total points</p>
                            <p className="mt-2 text-sm text-muted">Added up from the points of its questions.</p>
                        </div>
                    ) : (
                        <FormField id="total_points" label="Total points" error={errors.total_points} required>
                            <TextInput
                                type="number"
                                min="0"
                                step="0.5"
                                value={data.total_points}
                                onChange={(e) => setData('total_points', e.target.value)}
                            />
                        </FormField>
                    )}
                    <FormField id="title" label="Title" error={errors.title} required className="sm:col-span-2">
                        <TextInput value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    </FormField>
                    <FormField
                        id="description"
                        label="Short description"
                        error={errors.description}
                        hint="Shown in lists of activities."
                        className="sm:col-span-2"
                    >
                        <Textarea rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>
                    <FormField
                        id="instructions"
                        label="Instructions for students"
                        error={errors.instructions}
                        className="sm:col-span-2"
                    >
                        <Textarea rows={6} value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} />
                    </FormField>
                </div>
            </Card>

            <Card title="Schedule">
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="start_at"
                        label="Opens"
                        error={errors.start_at}
                        hint="Leave empty to open as soon as it is published."
                    >
                        <TextInput type="datetime-local" value={data.start_at} onChange={(e) => setData('start_at', e.target.value)} />
                    </FormField>
                    <FormField id="due_at" label="Due" error={errors.due_at} hint="Leave empty for no due date.">
                        <TextInput type="datetime-local" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} />
                    </FormField>
                    {usesQuestions && (
                        <FormField
                            id="duration_minutes"
                            label="Time limit (minutes)"
                            error={errors.duration_minutes}
                            hint="Counted from when each student starts. Leave empty for no time limit."
                        >
                            <TextInput
                                type="number"
                                min="1"
                                max="1440"
                                value={data.duration_minutes}
                                onChange={(e) => setData('duration_minutes', e.target.value)}
                            />
                        </FormField>
                    )}
                    {usesQuestions && (
                        <>
                            <FormField
                                id="max_attempts"
                                label="Attempts allowed"
                                error={errors.max_attempts}
                                hint="The best score counts."
                            >
                                <TextInput
                                    type="number"
                                    min="1"
                                    max="10"
                                    value={data.max_attempts}
                                    onChange={(e) => setData('max_attempts', e.target.value)}
                                />
                            </FormField>
                            <div className="space-y-3 sm:col-span-2">
                                <label className="flex items-start gap-3">
                                    <Checkbox
                                        checked={data.shuffle_questions}
                                        onChange={(e) => setData('shuffle_questions', e.target.checked)}
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block font-medium">Shuffle the questions</span>
                                        <span className="block text-sm text-muted">Each student gets the questions in a different order.</span>
                                    </span>
                                </label>
                                <label className="flex items-start gap-3">
                                    <Checkbox
                                        checked={data.shuffle_options}
                                        onChange={(e) => setData('shuffle_options', e.target.checked)}
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block font-medium">Shuffle the options</span>
                                        <span className="block text-sm text-muted">Options of each multiple choice question appear in a different order.</span>
                                    </span>
                                </label>
                            </div>
                        </>
                    )}
                    {takesWork && (
                        <label className="flex items-start gap-3 sm:col-span-2">
                            <Checkbox
                                checked={data.allow_late_submissions}
                                onChange={(e) => setData('allow_late_submissions', e.target.checked)}
                                className="mt-0.5"
                            />
                            <span>
                                <span className="block font-medium">Accept late work</span>
                                <span className="block text-sm text-muted">Students can still hand in after the due date. Their work is marked late.</span>
                            </span>
                        </label>
                    )}
                </div>
            </Card>

            <Card title="Files for students">
                <div className="space-y-4">
                    {activity && (
                        <AttachmentList attachments={activity.attachments} removing={data.remove_attachments} onToggleRemove={toggleRemove} />
                    )}
                    <FormField id="attachments" label="Add files" error={firstListError(errors, 'attachments')}>
                        <FileInput files={data.attachments} onChange={(files) => setData('attachments', files)} />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Save as draft'}
                </PrimaryButton>
                <Button variant="secondary" href={editing ? route('activities.show', activity.id) : route('activities.index')}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
