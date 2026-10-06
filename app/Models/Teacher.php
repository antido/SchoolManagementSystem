<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPersonName;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teacher's profile. The linked User is how they sign in.
 * `user_id` and `status` are set by TeacherService, never from a form.
 */
#[Fillable([
    'employee_number', 'first_name', 'middle_name', 'last_name',
    'birth_date', 'gender', 'contact_number', 'specialization',
])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use Auditable, HasFactory, HasPersonName;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The subjects this teacher teaches, in every class and term.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * The classes this teacher is (or was) attached to as adviser or teacher.
     *
     * @return HasMany<ClassTeacher, $this>
     */
    public function classAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacher::class);
    }

    /**
     * Whether the teacher has been assigned to a class or subject, or has
     * created learning activities. Such teachers are kept for history rather
     * than deleted.
     */
    public function hasHistory(): bool
    {
        return $this->classAssignments()->exists()
            || $this->classSubjects()->exists()
            || LearningActivity::query()->where('teacher_id', $this->id)->exists();
    }

    /**
     * Search by name, employee number, specialization or email.
     *
     * @param  Builder<Teacher>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query->whereContains('first_name', $search)
                ->orWhereContains('last_name', $search)
                ->orWhereContains('employee_number', $search)
                ->orWhereContains('specialization', $search)
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
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            'sortable_name' => $this->sortable_name,
            'email' => $this->user->email,
            'specialization' => $this->specialization,
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
            'status' => TeacherStatus::class,
        ];
    }

    public function auditLabel(): string
    {
        return $this->first_name.' '.$this->last_name.' ('.$this->employee_number.')';
    }
}
