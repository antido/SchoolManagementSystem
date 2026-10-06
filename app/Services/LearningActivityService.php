<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Events\ActivityPublished;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Creates learning activities and moves them through their life:
 * draft -> published -> closed (and closed -> published to reopen).
 */
class LearningActivityService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly AttemptService $attempts,
    ) {}

    /**
     * Create a draft activity. It belongs to whoever created it if they are
     * a teacher, otherwise to the class subject's teacher.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(ClassSubject $classSubject, User $creator, array $data): LearningActivity
    {
        $activity = new LearningActivity($this->normalise($data));
        $activity->classSubject()->associate($classSubject);
        $activity->teacher_id = $creator->teacher?->id ?? $classSubject->teacher_id;
        $activity->status = ActivityStatus::Draft;
        // Question-based activities are worth the sum of their questions, none yet.
        if ($activity->type->usesQuestions()) {
            $activity->total_points = 0;
        }
        $activity->save();

        $this->attachments->attach($activity, $data['attachments'] ?? [], $creator, "assignments/{$activity->id}");

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LearningActivity $activity, array $data, User $editor): LearningActivity
    {
        $activity->fill($this->normalise($data));
        if ($activity->type->usesQuestions()) {
            $activity->total_points = $activity->questions()->sum('points');
        }
        $activity->save();

        $this->attachments->detach($activity, $data['remove_attachments'] ?? []);
        $this->attachments->attach($activity, $data['attachments'] ?? [], $editor, "assignments/{$activity->id}");

        return $activity;
    }

    /**
     * Delete the activity and its files.
     */
    public function delete(LearningActivity $activity): void
    {
        $this->attachments->detachAll($activity);
        $activity->delete();
    }

    /**
     * Publish, close or reopen. An activity can only be published while its
     * due date is still ahead, so students always have time to work on it.
     *
     * @throws ValidationException
     */
    public function changeStatus(LearningActivity $activity, ActivityStatus $status): LearningActivity
    {
        if ($status === ActivityStatus::Published && $activity->isPastDue()) {
            throw ValidationException::withMessages([
                'status' => 'The due date has passed. Move the due date later before publishing.',
            ]);
        }

        $previous = $activity->status;
        $activity->status = $status;
        $activity->save();

        // Students hear about an activity the first time it is published, not when it is reopened.
        if ($status === ActivityStatus::Published && $previous === ActivityStatus::Draft) {
            ActivityPublished::dispatch($activity);
        }

        // Closing ends any attempt still in progress, keeping the answers saved so far.
        if ($status === ActivityStatus::Closed) {
            $this->attempts->closeAllFor($activity);
        }

        return $activity;
    }

    /**
     * Keep only the activity's own fields, with dates in the application's
     * time zone whatever zone the browser sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        foreach (['start_at', 'due_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field])
                    ? Carbon::parse($data[$field])->setTimezone(config('app.timezone'))
                    : null;
            }
        }

        if (array_key_exists('max_attempts', $data)) {
            $data['max_attempts'] = $data['max_attempts'] ?: 1;
        }

        unset($data['class_subject_id'], $data['attachments'], $data['remove_attachments']);

        return $data;
    }
}
