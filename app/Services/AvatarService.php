<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile pictures. They are kept on the private attachments disk under a
 * generated name and shown through the app to signed-in users only.
 */
class AvatarService
{
    /**
     * Store a new picture, removing the old one.
     */
    public function replace(User $user, UploadedFile $image): void
    {
        $this->remove($user);

        $user->forceFill(['avatar_path' => $image->store('avatars', $this->disk())])->save();
    }

    public function remove(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk($this->disk())->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    /**
     * Delete the file only, for when the account itself is being deleted.
     */
    public function deleteFile(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk($this->disk())->delete($user->avatar_path);
        }
    }

    public function response(User $user): StreamedResponse
    {
        abort_unless($user->avatar_path && Storage::disk($this->disk())->exists($user->avatar_path), 404);

        return Storage::disk($this->disk())->response($user->avatar_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            // The address changes when the picture does, so it can be cached.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function disk(): string
    {
        return config('school.attachments.disk');
    }
}
