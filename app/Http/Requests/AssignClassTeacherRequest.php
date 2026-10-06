<?php

namespace App\Http\Requests;

use App\Enums\ClassTeacherRole;
use App\Enums\TeacherStatus;
use App\Models\ClassTeacher;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignClassTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ClassTeacher::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => [
                'required', 'integer',
                Rule::exists('teachers', 'id')->whereIn('status', [TeacherStatus::Active->value, TeacherStatus::OnLeave->value]),
            ],
            'role' => ['required', Rule::enum(ClassTeacherRole::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SchoolClass $class */
                $class = $this->route('schoolClass');

                if (! $class->isActive()) {
                    $validator->errors()->add('teacher_id', 'This class is archived and cannot get new teachers.');
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
            'teacher_id.exists' => 'Choose an active teacher.',
        ];
    }
}
