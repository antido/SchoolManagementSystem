<?php

namespace App\Enums;

/**
 * Ways to react to a forum post.
 */
enum ReactionType: string
{
    case Like = 'like';
    case Helpful = 'helpful';
    case Thanks = 'thanks';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
