<?php

namespace App\Listeners;

use App\Events\ActivityPublished;
use App\Events\AttemptGraded;
use App\Events\AwardGranted;
use App\Events\ForumReplied;
use App\Events\SubmissionGraded;
use App\Events\TeacherAssigned;
use App\Events\WorkSubmitted;
use App\Models\ClassSubject;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ActivityPublishedNotification;
use App\Notifications\AwardReceivedNotification;
use App\Notifications\ExamResultNotification;
use App\Notifications\ForumReplyNotification;
use App\Notifications\TeacherAssignedNotification;
use App\Notifications\WorkGradedNotification;
use App\Notifications\WorkSubmittedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Turns school events into notifications for the people concerned.
 * Laravel finds the handle* methods by the event they take.
 *
 * It runs on the queue, so publishing an activity to a large class does not
 * slow the teacher's request, and only once the change that raised the
 * event has been committed.
 */
class SendSchoolNotifications implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    /**
     * Students currently in the class hear about a new activity.
     */
    public function handleActivityPublished(ActivityPublished $event): void
    {
        $activity = $event->activity->loadMissing('classSubject.subject');

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $activity->classSubject->school_class_id))
            ->with('user')
            ->get();

        Notification::send($this->usersOf($students), new ActivityPublishedNotification($activity));
    }

    /**
     * The class subject's teacher hears about work handed in.
     */
    public function handleWorkSubmitted(WorkSubmitted $event): void
    {
        $submission = $event->submission->loadMissing(['student', 'learningActivity.classSubject.teacher.user']);

        $submission->learningActivity->classSubject->teacher?->user?->notify(new WorkSubmittedNotification($submission));
    }

    public function handleSubmissionGraded(SubmissionGraded $event): void
    {
        $submission = $event->submission->loadMissing(['student.user', 'learningActivity']);

        $submission->student->user?->notify(new WorkGradedNotification($submission));
    }

    public function handleAttemptGraded(AttemptGraded $event): void
    {
        $attempt = $event->attempt->loadMissing(['student.user', 'learningActivity']);

        $attempt->student->user?->notify(new ExamResultNotification($attempt));
    }

    public function handleAwardGranted(AwardGranted $event): void
    {
        $studentAward = $event->studentAward->loadMissing(['student.user', 'award']);

        $studentAward->student->user?->notify(new AwardReceivedNotification($studentAward));
    }

    public function handleTeacherAssigned(TeacherAssigned $event): void
    {
        $assignment = $event->assignment;
        $assignment->loadMissing($assignment instanceof ClassSubject ? ['schoolClass', 'subject', 'teacher.user'] : ['schoolClass', 'teacher.user']);

        $assignment->teacher?->user?->notify(new TeacherAssignedNotification($assignment));
    }

    /**
     * A topic's author hears about replies (not their own).
     */
    public function handleForumReplied(ForumReplied $event): void
    {
        $post = $event->post->loadMissing(['author', 'topic.author']);

        if ($post->topic->user_id !== $post->user_id) {
            $post->topic->author->notify(new ForumReplyNotification($post));
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, User>
     */
    private function usersOf(Collection $students): Collection
    {
        return $students->pluck('user')->filter()->values();
    }
}
