<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in a class (table: class_students). Every column is set
 * by EnrollmentService, so nothing is fillable.
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use Auditable, HasFactory;

    protected $table = 'class_students';

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
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
     * Only students currently in the class.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('class_students.status', EnrollmentStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => EnrollmentStatus::class,
        ];
    }
}
