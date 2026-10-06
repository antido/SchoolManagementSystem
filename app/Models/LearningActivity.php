<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Concerns\Auditable;
use Database\Factories\LearningActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An assignment, quiz, examination, questionnaire or other activity given
 * to one class subject. Status changes go through LearningActivityService.
 */
#[Fillable(['title', 'description', 'type', 'instructions', 'start_at', 'due_at', 'duration_minutes', 'total_points', 'allow_late_submissions', 'max_attempts', 'shuffle_questions', 'shuffle_options'])]
class LearningActivity extends Model
{
    /** @use HasFactory<LearningActivityFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * The teacher who created it.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Students' handed-in work.
     *
     * @return HasMany<ActivitySubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ActivitySubmission::class);
    }

    /**
     * The questions, in the order students see them.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order')->orderBy('id');
    }

    /**
     * Students' sittings of a quiz, examination or questionnaire.
     *
     * @return HasMany<ActivityAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ActivityAttempt::class);
    }

    /**
     * Files the teacher attached (worksheets, readings).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Only the activities this person may see:
     * - staff who manage classes see all of them;
     * - teachers see the activities of the class subjects they teach, and
     *   the ones they created;
     * - students see published and closed activities that have started,
     *   in the classes they are currently enrolled in.
     *
     * Kinds the person has no view permission for are left out too.
     *
     * @param  Builder<LearningActivity>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('type', array_map(fn (ActivityType $type) => $type->value, ActivityType::allowedFor($user, 'view')));

        if (! $user->isLimitedToOwnClasses()) {
            return;
        }

        if ($user->teacher !== null) {
            $teacherId = $user->teacher->id;

            $query->where(function (Builder $query) use ($teacherId): void {
                $query->where('teacher_id', $teacherId)
                    ->orWhereHas('classSubject', fn (Builder $query) => $query->where('teacher_id', $teacherId));
            });

            return;
        }

        if ($user->student === null) {
            // A teacher or student account without its profile yet sees nothing.
            $query->whereRaw('1 = 0');

            return;
        }

        $studentId = $user->student->id;

        $query->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->where(fn (Builder $query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->whereHas('classSubject.schoolClass.enrollments', fn (Builder $query) => $query->active()->where('student_id', $studentId));
    }

    /**
     * Whether this person may see this activity (see scopeVisibleTo).
     */
    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    /**
     * Published activities whose due date has not passed, soonest first.
     *
     * @param  Builder<LearningActivity>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', ActivityStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->orderByRaw('due_at is null')
            ->orderBy('due_at');
    }

    /**
     * Search by title.
     *
     * @param  Builder<LearningActivity>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->whereContains('title', $search);
    }

    /**
     * What a student would see right now:
     * draft, scheduled (published, not started), open, past_due, or closed.
     */
    public function availability(): string
    {
        return match (true) {
            $this->status === ActivityStatus::Draft => 'draft',
            $this->status === ActivityStatus::Closed => 'closed',
            $this->start_at !== null && $this->start_at->isFuture() => 'scheduled',
            $this->isPastDue() => 'past_due',
            default => 'open',
        };
    }

    /**
     * Whether students can hand in work right now: it takes submissions,
     * is published and open, and is either not past due or accepts late work.
     */
    public function isAcceptingSubmissions(): bool
    {
        return $this->type->acceptsSubmissions()
            && $this->status === ActivityStatus::Published
            && ($this->start_at === null || ! $this->start_at->isFuture())
            && (! $this->isPastDue() || $this->allow_late_submissions);
    }

    /**
     * Whether students can start (or resume) an attempt right now: it is
     * made of questions, published, open, and not past its due date.
     */
    public function isAcceptingAttempts(): bool
    {
        return $this->type->usesQuestions()
            && $this->status === ActivityStatus::Published
            && ($this->start_at === null || ! $this->start_at->isFuture())
            && ! $this->isPastDue();
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Whether the questions can no longer change because students have
     * started answering them.
     */
    public function questionsAreLocked(): bool
    {
        return $this->attempts()->exists();
    }

    /**
     * Whether students have handed in work or attempted it.
     */
    public function hasDependents(): bool
    {
        return $this->submissions()->exists() || $this->attempts()->exists();
    }

    /**
     * Load `classSubject.subject`, `classSubject.schoolClass` and
     * `classSubject.academicTerm` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'availability' => $this->availability(),
            'start_at' => $this->start_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'total_points' => (float) $this->total_points,
            'allow_late_submissions' => $this->allow_late_submissions,
            'accepts_submissions' => $this->type->acceptsSubmissions(),
            'uses_questions' => $this->type->usesQuestions(),
            'max_attempts' => $this->max_attempts,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'class_subject' => [
                'id' => $this->classSubject->id,
                'subject' => $this->classSubject->subject->name,
                'school_class' => ['id' => $this->classSubject->schoolClass->id, 'name' => $this->classSubject->schoolClass->name],
                'term' => $this->classSubject->academicTerm->name,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'duration_minutes' => 'integer',
            'total_points' => 'decimal:2',
            'allow_late_submissions' => 'boolean',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
        ];
    }

    public function auditName(): string
    {
        return 'activity';
    }

    public function auditLabel(): string
    {
        return '"'.$this->title.'"';
    }
}
