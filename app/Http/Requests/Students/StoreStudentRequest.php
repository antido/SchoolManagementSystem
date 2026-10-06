<?php

namespace App\Http\Requests\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * The fields of the profile itself, shared with UpdateStudentRequest.
     *
     * @return array<string, array<mixed>>
     */
    protected function profileRules(?Student $student = null): array
    {
        return [
            'student_number' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique(Student::class)->ignore($student),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_number.unique' => 'Another student already has this student number.',
            'student_number.regex' => 'Use only letters, numbers and hyphens.',
            'contact_number.regex' => 'Use only numbers, spaces and + ( ) -.',
        ];
    }
}
