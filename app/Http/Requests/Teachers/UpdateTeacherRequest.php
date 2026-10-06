<?php

namespace App\Http\Requests\Teachers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeacherRequest extends StoreTeacherRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('teacher'));
    }

    /**
     * Status has its own action; the password may be left empty to keep it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Teacher $teacher */
        $teacher = $this->route('teacher');

        return [
            ...$this->profileRules($teacher),
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($teacher->user_id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
