<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Puts students into classes and takes them out again, keeping a full history.
 */
class EnrollmentService
{
    /**
     * Enroll several students in a class at once.
     *
     * Students who are not active, or who are already in this class, are
     * skipped rather than causing the whole request to fail.
     *
     * @param  list<int>  $studentIds
     * @return array{enrolled: int, skipped: int}
     */
    public function enroll(SchoolClass $class, array $studentIds): array
    {
        return DB::transaction(function () use ($class, $studentIds): array {
            // Lock the class's active roster so two people enrolling at the
            // same moment cannot create the same enrollment twice.
            $alreadyEnrolled = Enrollment::query()
                ->where('school_class_id', $class->id)
                ->active()
                ->lockForUpdate()
                ->pluck('student_id')
                ->all();

            $eligible = Student::query()
                ->whereIn('id', $studentIds)
                ->where('status', StudentStatus::Active)
                ->whereNotIn('id', $alreadyEnrolled)
                ->pluck('id');

            foreach ($eligible as $studentId) {
                $enrollment = new Enrollment;
                $enrollment->school_class_id = $class->id;
                $enrollment->student_id = $studentId;
                $enrollment->academic_year_id = $class->academic_year_id;
                $enrollment->enrolled_at = now();
                $enrollment->status = EnrollmentStatus::Active;
                $enrollment->save();
            }

            return [
                'enrolled' => $eligible->count(),
                'skipped' => count(array_unique($studentIds)) - $eligible->count(),
            ];
        });
    }

    /**
     * Take a student out of a class. The enrollment is kept as history.
     */
    public function withdraw(Enrollment $enrollment): void
    {
        $enrollment->status = EnrollmentStatus::Withdrawn;
        $enrollment->ended_at = now();
        $enrollment->save();
    }
}
