<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A message in a forum topic. Written by ForumService, so nothing is fillable.
 */
class ForumPost extends Model
{
    /** @use HasFactory<ForumPostFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ForumPostReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(ForumPostReaction::class);
    }

    /**
     * @return HasMany<ForumPostReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ForumPostReport::class);
    }

    /**
     * Files attached to the post.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Whether this is the topic's opening message.
     */
    public function isOpening(): bool
    {
        return ! ForumPost::withTrashed()
            ->where('forum_topic_id', $this->forum_topic_id)
            ->where(fn ($query) => $query->where('created_at', '<', $this->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $this->created_at)->where('id', '<', $this->id)))
            ->exists();
    }

    public function hasPendingReports(): bool
    {
        return $this->reports()->where('status', ReportStatus::Pending)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /**
     * Posts are recorded when edited or removed, not when written.
     *
     * @return list<string>
     */
    public function auditEvents(): array
    {
        return ['updated', 'deleted'];
    }
}
