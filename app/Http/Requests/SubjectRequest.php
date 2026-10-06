<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for creating and for editing a subject.
 */
class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique(Subject::class)->ignore($this->route('subject'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ActiveStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another subject already uses this code.',
            'code.regex' => 'Use only letters, numbers and hyphens.',
        ];
    }
}
