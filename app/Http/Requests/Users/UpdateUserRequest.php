<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * The same fields as creating, except: the password may be left empty to
     * keep it, and the role and status can only be changed for other people.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $isSelf = $this->user()->is($user);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => $isSelf ? ['prohibited'] : ['required', Rule::in($this->assignableRoles())],
            'is_active' => $isSelf ? ['prohibited'] : ['required', 'boolean'],
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
            ...parent::messages(),
            'role.prohibited' => 'You cannot change your own role.',
            'is_active.prohibited' => 'You cannot deactivate your own account.',
        ];
    }
}
