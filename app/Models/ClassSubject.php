<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ClassSubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject taught to one class in one term, by one teacher. Learning
 * activities, grades and attendance are all linked to a class subject.
 */
#[Fillable(['school_class_id', 'subject_id', 'teacher_id', 'academic_term_id', 'status'])]
class ClassSubject extends Model
{
    /** @use HasFactory<ClassSubjectFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The teacher, or null when nobody is assigned yet.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * @return HasMany<LearningActivity, $this>
     */
    public function learningActivities(): HasMany
    {
        return $this->hasMany(LearningActivity::class);
    }

    /**
     * The grading system chosen for it; null means the default one.
     *
     * @return BelongsTo<GradingSystem, $this>
     */
    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
    }

    /**
     * Every computed grade, current and superseded.
     *
     * @return HasMany<StudentGrade, $this>
     */
    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    /**
     * Only the class subjects this person may manage activities for: all of
     * them for staff who manage classes, the ones they teach for teachers,
     * and none for anyone else with a teacher or student profile.
     *
     * @param  Builder<ClassSubject>  $query
     */
    public function scopeManagedBy(Builder $query, User $user): void
    {
        if (! $user->isLimitedToOwnClasses()) {
            return;
        }

        if ($user->teacher === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('teacher_id', $user->teacher->id);
    }

    /**
     * Whether this person may manage this class subject's activities (see scopeManagedBy).
     */
    public function isManagedBy(User $user): bool
    {
        return ! $user->isLimitedToOwnClasses()
            || ($user->teacher !== null && $this->teacher_id === $user->teacher->id);
    }

    /**
     * Whether learning activities or grades exist for it. Extended as those
     * modules are added.
     */
    public function hasDependents(): bool
    {
        return $this->learningActivities()->exists() || $this->studentGrades()->exists();
    }

    /**
     * "Mathematics 7 - Grade 7 Rizal (First Semester)". Load the relations first.
     */
    public function label(): string
    {
        return "{$this->subject->name} - {$this->schoolClass->name} ({$this->academicTerm->name})";
    }

    /**
     * Load `subject`, `schoolClass`, `teacher` and `academicTerm` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'subject' => ['id' => $this->subject->id, 'code' => $this->subject->code, 'name' => $this->subject->name],
            'school_class' => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name],
            'term' => ['id' => $this->academicTerm->id, 'name' => $this->academicTerm->name],
            'teacher' => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->full_name] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }
}
