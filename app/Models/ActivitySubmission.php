<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\ActivitySubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A student's work for one activity. All columns are set by
 * SubmissionService, so nothing is fillable.
 */
class ActivitySubmission extends Model
{
    /** @use HasFactory<ActivitySubmissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LearningActivity, $this>
     */
    public function learningActivity(): BelongsTo
    {
        return $this->belongsTo(LearningActivity::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isGraded(): bool
    {
        return $this->status === SubmissionStatus::Graded;
    }

    /**
     * The status to show for a student's work on an activity, whether or
     * not they have handed anything in: graded, late, submitted, missing
     * (past due, nothing handed in) or pending.
     */
    public static function displayStatus(?self $submission, LearningActivity $activity): string
    {
        return match (true) {
            $submission?->isGraded() => 'graded',
            $submission?->is_late => 'late',
            $submission !== null => 'submitted',
            $activity->isPastDue() => 'missing',
            default => 'pending',
        };
    }

    /**
     * Load `attachments` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'status' => self::displayStatus($this, $this->learningActivity),
            'body' => $this->body,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'is_late' => $this->is_late,
            'score' => $this->score === null ? null : (float) $this->score,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'attachments' => $this->attachments->map(fn (Attachment $attachment) => $attachment->summary())->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'score' => 'decimal:2',
        ];
    }
}
