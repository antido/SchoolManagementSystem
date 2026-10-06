<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An attendance sheet: one day, the whole class or one subject, and a
 * status for each student currently in the class.
 */
class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record', [Attendance::class, $this->schoolClass(), $this->classSubject()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $class = $this->schoolClass();
        $year = $class->academicYear;

        return [
            'date' => [
                'required', 'date_format:Y-m-d', 'before_or_equal:today',
                // Within the class's academic year.
                'after_or_equal:'.$year->start_date->toDateString(),
                'before_or_equal:'.$year->end_date->toDateString(),
            ],
            'class_subject_id' => ['nullable', 'integer', Rule::exists('class_subjects', 'id')->where('school_class_id', $class->id)],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => [
                'required', 'integer', 'distinct',
                // Only students currently in the class.
                Rule::exists('class_students', 'student_id')->where('school_class_id', $class->id)->where('status', 'active'),
            ],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Choose a date that is not in the future and falls within the class\'s academic year.',
            'date.after_or_equal' => 'Choose a date within the class\'s academic year.',
            'records.*.student_id.exists' => 'One of the students is no longer in this class. Reload the page.',
            'records.*.status.required' => 'Mark every student.',
        ];
    }

    public function schoolClass(): SchoolClass
    {
        return $this->route('schoolClass');
    }

    public function classSubject(): ?ClassSubject
    {
        $id = $this->input('class_subject_id');

        return $id ? ClassSubject::find((int) $id) : null;
    }
}
