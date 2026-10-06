<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;

/**
 * Anyone who may see the record a file is attached to may download it.
 * Files on records without a policy cannot be downloaded.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $this->attachable($attachment);

        return $attachable !== null
            && Gate::getPolicyFor($attachable) !== null
            && $user->can('view', $attachable);
    }

    /**
     * The record, including a soft-deleted one (a removed forum post), so
     * its own policy decides; for removed posts that means moderators only.
     */
    private function attachable(Attachment $attachment): ?Model
    {
        $class = $attachment->getActualClassNameForMorph($attachment->attachable_type);

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $attachment->attachable
            ?? (in_array(SoftDeletes::class, class_uses_recursive($class), true) ? $class::withTrashed()->find($attachment->attachable_id) : null);
    }
}
