<?php

namespace App\Http\Requests;

use App\Models\Award;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Giving an award to a student, optionally for an academic year and one of
 * the student's classes.
 */
class GrantAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Award $award */
        $award = $this->route('award');
        $student = Student::find((int) $this->input('student_id'));

        // A missing student is reported by validation instead.
        return $this->user()->can('grant', [$award, $student]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => [
                'nullable', 'integer',
                // Only a class the student is or was in.
                Rule::exists('class_students', 'school_class_id')->where('student_id', (int) $this->input('student_id')),
            ],
            'awarded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_class_id.exists' => 'Choose one of the student\'s classes.',
            'awarded_on.before_or_equal' => 'The date cannot be in the future.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'student',
            'academic_year_id' => 'academic year',
            'school_class_id' => 'class',
            'awarded_on' => 'date',
        ];
    }
}
