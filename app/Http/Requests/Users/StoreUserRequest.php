<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Only a super admin may create another super admin.
     *
     * @return list<string>
     */
    protected function assignableRoles(): array
    {
        $roles = array_map(fn (RoleName $role) => $role->value, RoleName::cases());

        if (! $this->user()->hasRole(RoleName::SuperAdmin->value)) {
            $roles = array_values(array_diff($roles, [RoleName::SuperAdmin->value]));
        }

        return $roles;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Choose one of the roles in the list.',
        ];
    }
}
