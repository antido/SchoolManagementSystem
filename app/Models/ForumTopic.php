<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A forum discussion. Its first post is the opening message. Written by
 * ForumService, so nothing is fillable.
 */
class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<ForumCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * All posts, oldest first, including removed ones (shown as removed).
     *
     * @return HasMany<ForumPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->withTrashed()->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasOne<ForumPost, $this>
     */
    public function firstPost(): HasOne
    {
        return $this->hasOne(ForumPost::class)->oldestOfMany();
    }

    /**
     * Pinned topics first, then the most recently active.
     *
     * @param  Builder<ForumTopic>  $query
     */
    public function scopeInListOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('last_posted_at')->orderByDesc('id');
    }

    /**
     * Topics whose title or any remaining post mentions the text.
     *
     * @param  Builder<ForumTopic>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query->whereContains('title', $search)
                ->orWhereHas('posts', fn (Builder $query) => $query->whereNull('deleted_at')->whereContains('body', $search));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'replies_count' => 'integer',
            'last_posted_at' => 'datetime',
        ];
    }

    public function auditLabel(): string
    {
        return '"'.$this->title.'"';
    }

    /**
     * Topics are recorded when changed or removed (moderation), not when started.
     *
     * @return list<string>
     */
    public function auditEvents(): array
    {
        return ['updated', 'deleted'];
    }

    /**
     * @return list<string>
     */
    public function auditIgnore(): array
    {
        return ['replies_count', 'last_posted_at'];
    }
}
