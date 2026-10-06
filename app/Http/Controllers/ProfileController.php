<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\AvatarService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'canDeleteAccount' => $this->canDeleteOwnAccount($request->user()),
        ]);
    }

    /**
     * People with a student or teacher record keep their account for the
     * school's history, and administrators cannot remove themselves (the
     * last super-admin would lock everyone out). Both ask an administrator.
     */
    private function canDeleteOwnAccount(User $user): bool
    {
        return $user->student === null
            && $user->teacher === null
            && ! $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Upload or replace the profile picture.
     */
    public function updateAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate(['avatar' => ['required', ...AttachmentService::imageRules()]]);

        $avatars->replace($request->user(), $request->file('avatar'));

        return back()->with('success', 'Your picture was updated.');
    }

    public function destroyAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->remove($request->user());

        return back()->with('success', 'Your picture was removed.');
    }

    /**
     * Show someone's profile picture to a signed-in user.
     */
    public function showAvatar(User $user, AvatarService $avatars): StreamedResponse
    {
        return $avatars->response($user);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (! $this->canDeleteOwnAccount($request->user())) {
            return back()->with('error', 'Your account is part of the school\'s records. Ask an administrator to deactivate it.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
