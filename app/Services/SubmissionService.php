<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Events\SubmissionGraded;
use App\Events\WorkSubmitted;
use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Students handing in work, and teachers grading it.
 */
class SubmissionService
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Hand in work, or replace work handed in earlier. Work handed in after
     * the due date is marked late.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, int|string>  $removeAttachmentIds
     *
     * @throws ValidationException when the work was already graded
     */
    public function submit(
        LearningActivity $activity,
        Student $student,
        ?string $body,
        array $files,
        array $removeAttachmentIds,
        User $user,
    ): ActivitySubmission {
        $submission = DB::transaction(function () use ($activity, $student, $body, $files, $removeAttachmentIds, $user) {
            $submission = ActivitySubmission::query()
                ->where('learning_activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first() ?? new ActivitySubmission;

            if ($submission->exists && $submission->isGraded()) {
                throw ValidationException::withMessages(['body' => 'This work has been graded and can no longer be changed.']);
            }

            $submission->learning_activity_id = $activity->id;
            $submission->student_id = $student->id;
            $submission->body = $body;
            $submission->status = SubmissionStatus::Submitted;
            $submission->submitted_at = now();
            $submission->is_late = $activity->isPastDue();
            $submission->save();

            $this->attachments->detach($submission, $removeAttachmentIds);
            $this->attachments->attach($submission, $files, $user, "submissions/{$activity->id}");

            return $submission;
        });

        WorkSubmitted::dispatch($submission);

        return $submission;
    }

    /**
     * Score the work and give feedback. Grading again replaces the score.
     */
    public function grade(ActivitySubmission $submission, float $score, ?string $feedback, User $grader): ActivitySubmission
    {
        $submission->score = $score;
        $submission->feedback = $feedback;
        $submission->status = SubmissionStatus::Graded;
        $submission->graded_by = $grader->id;
        $submission->graded_at = now();
        $submission->save();

        SubmissionGraded::dispatch($submission);

        return $submission;
    }
}
