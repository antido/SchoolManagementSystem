<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnrollStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')],
        ];
    }

    /**
     * Archived classes take no new students.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SchoolClass $class */
                $class = $this->route('schoolClass');

                if (! $class->isActive()) {
                    $validator->errors()->add('student_ids', 'This class is archived and cannot take new students.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_ids.required' => 'Choose at least one student.',
        ];
    }
}
