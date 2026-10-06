<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPersonName;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's profile. The linked User is how they sign in.
 *
 * `user_id` and `status` are not fillable: the account link is made by
 * StudentService, and status changes go through their own action.
 */
#[Fillable([
    'student_number', 'first_name', 'middle_name', 'last_name',
    'birth_date', 'gender', 'address', 'contact_number',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use Auditable, HasFactory, HasPersonName;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every class the student has been in, current and past.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Awards received, including revoked ones.
     *
     * @return HasMany<StudentAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(StudentAward::class);
    }

    /**
     * Whether any school record refers to the student (classes, work,
     * attendance, grades or awards). Such students are kept for history.
     */
    public function hasHistory(): bool
    {
        return $this->enrollments()->exists()
            || $this->awards()->exists()
            || ActivitySubmission::query()->where('student_id', $this->id)->exists()
            || ActivityAttempt::query()->where('student_id', $this->id)->exists()
            || Attendance::query()->where('student_id', $this->id)->exists()
            || StudentGrade::query()->where('student_id', $this->id)->exists();
    }

    /**
     * Only the students this person may see: everyone for staff who manage
     * students, and the students of their own classes for teachers.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('students.update') || ! $user->isLimitedToOwnClasses()) {
            return;
        }

        $classIds = SchoolClass::query()->visibleTo($user)->pluck('id');

        $query->whereHas('enrollments', fn (Builder $query) => $query->whereIn('school_class_id', $classIds));
    }

    /**
     * Search by name, student number or email.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query->whereContains('first_name', $search)
                ->orWhereContains('last_name', $search)
                ->orWhereContains('student_number', $search)
                ->orWhereHas('user', fn (Builder $query) => $query->whereContains('email', $search));
        });
    }

    /**
     * The fields shown in lists. Load `user` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'full_name' => $this->full_name,
            'sortable_name' => $this->sortable_name,
            'email' => $this->user->email,
            'status' => $this->status->value,
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
            'birth_date' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
        ];
    }

    public function auditLabel(): string
    {
        return $this->first_name.' '.$this->last_name.' ('.$this->student_number.')';
    }
}
