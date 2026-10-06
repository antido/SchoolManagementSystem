<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * The business rules for user accounts. Controllers validate and authorize;
 * this class does the work, so the same rules apply wherever accounts are
 * created (the admin screens, student and teacher creation, seeders).
 */
class UserService
{
    /**
     * Create an account with one role.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data, string $role, bool $isActive = true): User
    {
        return DB::transaction(function () use ($data, $role, $isActive): User {
            $user = new User($data);
            $user->is_active = $isActive;
            // Accounts created by the school are trusted addresses.
            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles([$role]);

            return $user;
        });
    }

    /**
     * Change the name, email and (optionally) password.
     *
     * @param  array{name: string, email: string, password?: string|null}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }

    /**
     * Give the account exactly this one role.
     */
    public function assignRole(User $user, string $role): void
    {
        $user->syncRoles([$role]);
    }

    /**
     * Activate or deactivate the account. A deactivated user is signed out
     * on their next request and cannot sign in again (see EnsureAccountIsActive).
     */
    public function setActive(User $user, bool $isActive): void
    {
        $user->is_active = $isActive;
        $user->save();
    }

    /**
     * Email the user a link to choose a new password.
     * Returns Laravel's status message key, for example "passwords.sent".
     */
    public function sendPasswordResetLink(User $user): string
    {
        return Password::sendResetLink(['email' => $user->email]);
    }
}
