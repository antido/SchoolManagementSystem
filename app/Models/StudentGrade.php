<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A computed grade for one student in one class subject. Rows are written
 * by GradeService and never edited: a newer grade supersedes an older one.
 */
class StudentGrade extends Model
{
    use Auditable;

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
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
    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * Only the latest grade of each student and class subject.
     *
     * @param  Builder<StudentGrade>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'percentage' => (float) $this->percentage,
            'descriptor' => $this->descriptor,
            'passed' => $this->passed,
            'breakdown' => $this->breakdown,
            'computed_at' => $this->computed_at->toIso8601String(),
            'superseded_at' => $this->superseded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function auditName(): string
    {
        return 'grade';
    }

    /**
     * A grade is recorded when computed; marking the old one superseded is
     * part of the same change.
     *
     * @return list<string>
     */
    public function auditEvents(): array
    {
        return ['created'];
    }

    /**
     * The breakdown is bulky and can be rebuilt from the scores.
     *
     * @return list<string>
     */
    public function auditIgnore(): array
    {
        return ['breakdown'];
    }

    /**
     * The grade this one replaces, so the trail shows the change.
     *
     * @return array<string, mixed>|null
     */
    public function auditOldValuesForCreate(): ?array
    {
        $previous = static::query()
            ->where('class_subject_id', $this->class_subject_id)
            ->where('student_id', $this->student_id)
            ->whereKeyNot($this->id)
            ->latest('id')
            ->first(['percentage', 'descriptor', 'passed']);

        return $previous?->only(['percentage', 'descriptor', 'passed']);
    }
}
