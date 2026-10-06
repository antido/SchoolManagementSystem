<?php

namespace App\Policies;

use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;

/**
 * Reading, starting and replying to topics, and moderating them.
 * Locked topics take replies from moderators only; removed topics are
 * visible to moderators only.
 */
class ForumTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('forum.view');
    }

    public function view(User $user, ForumTopic $topic): bool
    {
        return $user->can('forum.view') && (! $topic->trashed() || $user->can('forum.moderate'));
    }

    public function create(User $user): bool
    {
        return $user->can('forum.participate');
    }

    public function reply(User $user, ForumTopic $topic): bool
    {
        return $user->can('forum.participate')
            && ! $topic->trashed()
            && (! $topic->is_locked || $user->can('forum.moderate'));
    }

    /**
     * Pin, lock and remove any topic, and handle reports.
     */
    public function moderate(User $user): bool
    {
        return $user->can('forum.moderate');
    }

    /**
     * Remove the whole topic: moderators, or its author while nobody has replied.
     */
    public function delete(User $user, ForumTopic $topic): bool
    {
        if ($topic->trashed()) {
            return false;
        }

        return $user->can('forum.moderate')
            || ($topic->user_id === $user->id && $user->can('forum.participate') && $topic->replies_count === 0);
    }

    /**
     * Add, rename and remove categories: moderators who are not limited to
     * their own classes, i.e. administrators.
     */
    public function manageCategories(User $user, ?ForumCategory $category = null): bool
    {
        return $user->can('forum.moderate') && ! $user->isLimitedToOwnClasses();
    }
}
