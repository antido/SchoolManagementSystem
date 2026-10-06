<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;

/**
 * What people may do with a post. Authors edit their own posts while the
 * topic is open; moderators remove posts but never rewrite them.
 */
class ForumPostPolicy
{
    /**
     * See the post (and download its files): removed posts and posts in
     * removed topics are for moderators only.
     */
    public function view(User $user, ForumPost $post): bool
    {
        if (! $user->can('forum.view')) {
            return false;
        }

        return (! $post->trashed() && ! $post->topic->trashed()) || $user->can('forum.moderate');
    }

    public function update(User $user, ForumPost $post): bool
    {
        return $post->user_id === $user->id
            && $user->can('forum.participate')
            && ! $post->trashed()
            && ! $post->topic->trashed()
            && ! $post->topic->is_locked;
    }

    /**
     * Remove: moderators any post; authors their own replies.
     */
    public function delete(User $user, ForumPost $post): bool
    {
        if ($post->trashed() || $post->topic->trashed()) {
            return false;
        }

        return $user->can('forum.moderate')
            || ($post->user_id === $user->id && $user->can('forum.participate') && ! $post->isOpening());
    }

    public function react(User $user, ForumPost $post): bool
    {
        return $user->can('forum.participate') && ! $post->trashed() && ! $post->topic->trashed();
    }

    /**
     * Report someone else's post, once.
     */
    public function report(User $user, ForumPost $post): bool
    {
        return $user->can('forum.participate')
            && $post->user_id !== $user->id
            && ! $post->trashed()
            && ! $post->reports()->where('user_id', $user->id)->exists();
    }
}
