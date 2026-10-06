<?php

namespace App\Http\Requests;

use App\Enums\ActivityType;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use App\Services\AttachmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating or editing a learning activity. Dates arrive as ISO 8601 strings
 * from the browser, so they keep the teacher's time zone.
 */
class LearningActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = ActivityType::tryFrom((string) $this->input('type'));

        /** @var LearningActivity|null $activity */
        $activity = $this->route('activity');

        if ($activity) {
            // Changing the kind also needs permission for the new kind.
            return $this->user()->can('update', $activity)
                && ($type === null || $this->user()->can($type->permission('update')));
        }

        $classSubject = ClassSubject::find((int) $this->input('class_subject_id'));

        // Missing or invalid input is reported by validation instead.
        if ($type === null || $classSubject === null) {
            return ActivityType::allowedFor($this->user(), 'create') !== [];
        }

        return $this->user()->can('create', [LearningActivity::class, $classSubject, $type]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The class subject is chosen when creating and cannot change.
            'class_subject_id' => $this->route('activity') ? ['prohibited'] : ['required', 'integer', 'exists:class_subjects,id'],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', Rule::when($this->filled('start_at'), 'after:start_at')],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'total_points' => ['required', 'numeric', 'min:0', 'max:10000'],
            'allow_late_submissions' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            ...AttachmentService::rules(),
        ];
    }

    /**
     * No more files than allowed, counting the ones already attached, and
     * no change of kind that would orphan work or questions.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $count = app(AttachmentService::class)->countAfter(
                    $this->route('activity'),
                    $this->input('remove_attachments', []),
                    count($this->file('attachments', [])),
                );

                if ($count > config('school.attachments.max_files')) {
                    $validator->errors()->add('attachments', 'You can attach up to '.config('school.attachments.max_files').' files.');
                }

                /** @var LearningActivity|null $activity */
                $activity = $this->route('activity');
                $type = ActivityType::tryFrom((string) $this->input('type'));

                // The kind decides what students hand in, so it is fixed once work or questions exist.
                if ($activity && $type && $type !== $activity->type) {
                    if ($activity->hasDependents()) {
                        $validator->errors()->add('type', 'Students have already worked on this activity, so its kind cannot change.');
                    } elseif (! $type->usesQuestions() && $activity->questions()->exists()) {
                        $validator->errors()->add('type', 'Remove the questions before changing to a kind without questions.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.after' => 'The due date must be after the start date.',
            'duration_minutes.max' => 'The time limit cannot be more than 24 hours (1440 minutes).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_subject_id' => 'class subject',
            'start_at' => 'start date',
            'due_at' => 'due date',
            'duration_minutes' => 'time limit',
            'max_attempts' => 'attempts allowed',
            'total_points' => 'total points',
            'attachments.*' => 'file',
        ];
    }
}
