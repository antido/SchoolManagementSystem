<?php

namespace App\Models;

use App\Enums\ClassTeacherRole;
use App\Models\Concerns\Auditable;
use Database\Factories\ClassTeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher attached to a class for an academic year (table: class_teachers).
 * An assignment is current while ended_at is empty. All columns are set by
 * ClassTeacherService, so nothing is fillable.
 */
class ClassTeacher extends Model
{
    /** @use HasFactory<ClassTeacherFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Only assignments that have not ended.
     *
     * @param  Builder<ClassTeacher>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('class_teachers.ended_at');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ClassTeacherRole::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
