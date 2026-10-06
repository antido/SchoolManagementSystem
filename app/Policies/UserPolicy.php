<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Who may manage which user accounts.
 *
 * Two extra rules prevent privilege escalation:
 * - only a super admin may change or delete a super admin account;
 * - nobody may deactivate, delete or change the role of their own account.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update') && $this->mayTouch($actor, $user);
    }

    /**
     * Activate or deactivate the account.
     */
    public function changeStatus(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $actor->isNot($user);
    }

    /**
     * Change which role the account has.
     */
    public function assignRole(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $actor->isNot($user);
    }

    /**
     * Send the account a password reset link.
     */
    public function resetPassword(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    /**
     * Accounts that belong to a student or teacher profile are removed
     * together with the profile (on the Students or Teachers screens),
     * never on their own.
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.delete')
            && $actor->isNot($user)
            && $this->mayTouch($actor, $user)
            && $user->student()->doesntExist()
            && $user->teacher()->doesntExist();
    }

    /**
     * Only a super admin may manage another super admin.
     */
    private function mayTouch(User $actor, User $user): bool
    {
        return ! $user->hasRole(RoleName::SuperAdmin->value)
            || $actor->hasRole(RoleName::SuperAdmin->value);
    }
}
