<?php

namespace App\Notifications;

use App\Models\ForumPost;

/**
 * To a topic's author: someone replied.
 */
class ForumReplyNotification extends SchoolNotification
{
    /**
     * Load `author` and `topic` first.
     */
    public function __construct(private readonly ForumPost $post) {}

    protected function title(): string
    {
        return "{$this->post->author->name} replied to {$this->post->topic->title}";
    }

    protected function url(): string
    {
        return route('forum.topics.show', $this->post->forum_topic_id, false).'#post-'.$this->post->id;
    }

    protected function icon(): string
    {
        return 'chat';
    }
}
