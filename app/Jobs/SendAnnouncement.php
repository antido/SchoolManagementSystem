<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers an announcement to its audience in batches, on the queue, so
 * announcing to the whole school does not hold up the sender's request.
 */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{audience: string, role?: string|null, school_class_id?: int|string|null}  $audience
     */
    public function __construct(
        public array $audience,
        public string $title,
        public string $message,
        public string $sender,
    ) {}

    public function handle(): void
    {
        static::recipients($this->audience)->chunkById(200, function ($users): void {
            Notification::send($users, new AnnouncementNotification($this->title, $this->message, $this->sender));
        });
    }

    /**
     * Active accounts in the audience: everyone, everyone with a role, or a
     * class's current students and teachers.
     *
     * @param  array{audience: string, role?: string|null, school_class_id?: int|string|null}  $audience
     * @return Builder<User>
     */
    public static function recipients(array $audience): Builder
    {
        $query = User::query()->where('is_active', true);

        return match ($audience['audience']) {
            'role' => $query->role($audience['role']),
            'class' => $query->where(function (Builder $query) use ($audience): void {
                $classId = $audience['school_class_id'];

                $query->whereHas('student.enrollments', fn ($query) => $query->active()->where('school_class_id', $classId))
                    ->orWhereHas('teacher.classAssignments', fn ($query) => $query->current()->where('school_class_id', $classId))
                    ->orWhereHas('teacher.classSubjects', fn ($query) => $query->where('school_class_id', $classId));
            }),
            default => $query,
        };
    }
}
