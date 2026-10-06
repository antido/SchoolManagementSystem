<?php

namespace App\Models;

use App\Enums\ClassStatus;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class or section, for example "Grade 7 - Sampaguita", within one
 * academic year. Called SchoolClass because "Class" is reserved in PHP.
 */
#[Fillable(['academic_year_id', 'name', 'code', 'grade_level', 'section', 'description'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Teachers attached to the class, current and past.
     *
     * @return HasMany<ClassTeacher, $this>
     */
    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class);
    }

    /**
     * Only the classes this person may see: all of them for staff, the
     * classes they teach (now or before) for teachers, and the classes they
     * are or were enrolled in for students.
     *
     * @param  Builder<SchoolClass>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isLimitedToOwnClasses()) {
            return;
        }

        if ($user->teacher !== null) {
            $teacherId = $user->teacher->id;

            $query->where(function (Builder $query) use ($teacherId): void {
                $query->whereHas('classTeachers', fn (Builder $query) => $query->where('teacher_id', $teacherId))
                    ->orWhereHas('classSubjects', fn (Builder $query) => $query->where('teacher_id', $teacherId));
            });

            return;
        }

        if ($user->student !== null) {
            $query->whereHas('enrollments', fn (Builder $query) => $query->where('student_id', $user->student->id));

            return;
        }

        // A teacher or student account without its profile yet sees no classes.
        $query->whereRaw('1 = 0');
    }

    /**
     * Whether this person may see this class (see scopeVisibleTo).
     */
    public function isVisibleTo(User $user): bool
    {
        return ! $user->isLimitedToOwnClasses()
            || static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    /**
     * Search by name, code or section.
     *
     * @param  Builder<SchoolClass>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query->whereContains('name', $search)
                ->orWhereContains('code', $search)
                ->orWhereContains('section', $search);
        });
    }

    public function isActive(): bool
    {
        return $this->status === ClassStatus::Active;
    }

    /**
     * Whether other records depend on this class, in which case it is
     * archived rather than deleted. Extended as later modules attach to classes.
     */
    public function hasDependents(): bool
    {
        return $this->classSubjects()->exists()
            || $this->enrollments()->exists()
            || $this->classTeachers()->exists();
    }

    /**
     * Every enrollment in this class, current and past.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The subjects this class takes, per term.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * The fields shown in lists. Load `academicYear` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'grade_level' => $this->grade_level,
            'section' => $this->section,
            'status' => $this->status->value,
            'academic_year' => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => $this->academicYear->is_active,
            ],
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClassStatus::class,
        ];
    }
}
