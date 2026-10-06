<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\StudentAwardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An award given to a student. Written by AwardService; revoked rather
 * than deleted, so nothing is fillable.
 */
class StudentAward extends Model
{
    /** @use HasFactory<StudentAwardFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Award, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /**
     * @param  Builder<StudentAward>  $query
     */
    public function scopeNotRevoked(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Load `award.attachments`, `academicYear`, `schoolClass` and `awardedBy` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'award' => $this->award->summary(),
            'awarded_on' => $this->awarded_on->toDateString(),
            'remarks' => $this->remarks,
            'academic_year' => $this->academicYear?->name,
            'school_class' => $this->schoolClass ? ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name] : null,
            'awarded_by' => $this->awardedBy?->name,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_reason' => $this->revoked_reason,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function auditName(): string
    {
        return 'award_given';
    }
}
