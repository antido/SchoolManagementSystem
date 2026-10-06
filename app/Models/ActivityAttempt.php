<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Database\Factories\ActivityAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sitting of a quiz, examination or questionnaire by a student. All
 * columns are set by AttemptService, so nothing is fillable.
 */
class ActivityAttempt extends Model
{
    /** @use HasFactory<ActivityAttemptFactory> */
    use HasFactory;

    /**
     * Seconds after expires_at during which answers are still accepted, to
     * allow for the last autosave being on its way when time runs out.
     */
    public const GRACE_SECONDS = 15;

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
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * @param  Builder<ActivityAttempt>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', AttemptStatus::InProgress);
    }

    /**
     * Attempts still open although their time is up (plus the grace period).
     *
     * @param  Builder<ActivityAttempt>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->inProgress()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subSeconds(self::GRACE_SECONDS));
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /**
     * Whether the time is up, allowing the grace period.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->copy()->addSeconds(self::GRACE_SECONDS)->isPast();
    }

    /**
     * Seconds left, or null when there is no time limit.
     */
    public function secondsRemaining(): ?int
    {
        return $this->expires_at === null ? null : max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'started_at' => $this->started_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'auto_submitted' => $this->auto_submitted,
            'score' => $this->score === null ? null : (float) $this->score,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'score' => 'decimal:2',
            'question_order' => 'array',
            'option_order' => 'array',
        ];
    }
}
