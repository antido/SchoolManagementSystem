<?php

namespace App\Http\Requests;

use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Services\AttachmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A student handing in (or replacing) their work for an activity.
 */
class SubmitWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('activity'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:20000'],
            ...AttachmentService::rules(),
        ];
    }

    /**
     * Work needs an answer or at least one file, and no more files than allowed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fileCount = app(AttachmentService::class)->countAfter(
                    $this->existingSubmission(),
                    $this->input('remove_attachments', []),
                    count($this->file('attachments', [])),
                );

                if ($fileCount > config('school.attachments.max_files')) {
                    $validator->errors()->add('attachments', 'You can attach up to '.config('school.attachments.max_files').' files.');
                }

                if (blank($this->input('body')) && $fileCount === 0) {
                    $validator->errors()->add('body', 'Write an answer or attach a file.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'answer',
            'attachments.*' => 'file',
        ];
    }

    private function existingSubmission(): ?ActivitySubmission
    {
        /** @var LearningActivity $activity */
        $activity = $this->route('activity');

        return $activity->submissions()->where('student_id', $this->user()->student?->id)->first();
    }
}
