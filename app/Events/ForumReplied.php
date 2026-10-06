<?php

namespace App\Events;

use App\Models\ForumPost;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone replied in a forum topic.
 */
class ForumReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public ForumPost $post) {}
}
