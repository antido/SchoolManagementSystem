<?php

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's reaction to a post. Each person can give each kind once.
 */
class ForumPostReaction extends Model
{
    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReactionType::class,
        ];
    }
}
