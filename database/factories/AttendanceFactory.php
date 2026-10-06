<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Present today, daily attendance.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'student_id' => Student::factory(),
            'class_subject_id' => null,
            'teacher_id' => null,
            'attendance_date' => today(),
            'status' => AttendanceStatus::Present,
            'remarks' => null,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
