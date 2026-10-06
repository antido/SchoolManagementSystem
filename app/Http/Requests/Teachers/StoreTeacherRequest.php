<?php

namespace App\Http\Requests\Teachers;

use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Teacher::class);
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
            'status' => ['required', Rule::enum(TeacherStatus::class)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * The profile fields, shared with UpdateTeacherRequest.
     *
     * @return array<string, array<mixed>>
     */
    protected function profileRules(?Teacher $teacher = null): array
    {
        return [
            'employee_number' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique(Teacher::class)->ignore($teacher),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'specialization' => ['nullable', 'string', 'max:150'],
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
            'employee_number.unique' => 'Another teacher already has this employee number.',
            'employee_number.regex' => 'Use only letters, numbers and hyphens.',
            'contact_number.regex' => 'Use only numbers, spaces and + ( ) -.',
        ];
    }
}
