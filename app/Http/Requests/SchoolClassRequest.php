<?php

namespace App\Http\Requests;

use App\Enums\ClassStatus;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for creating and for editing a class.
 */
class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('schoolClass');

        return $class
            ? $this->user()->can('update', $class)
            : $this->user()->can('create', SchoolClass::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'name' => ['required', 'string', 'max:100'],
            // A code may repeat across years but not within one year.
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique(SchoolClass::class)
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->ignore($this->route('schoolClass')),
            ],
            'grade_level' => ['required', 'string', 'max:30'],
            'section' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ClassStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another class in this academic year already uses this code.',
            'code.regex' => 'Use only letters, numbers and hyphens.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['academic_year_id' => 'academic year'];
    }
}
