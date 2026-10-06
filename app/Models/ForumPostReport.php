<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone flagged a post for the moderators. Each person can report a post once.
 */
class ForumPostReport extends Model
{
    use Auditable;

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function auditName(): string
    {
        return 'forum_report';
    }

    /**
     * Reports are recorded when a moderator handles them.
     *
     * @return list<string>
     */
    public function auditEvents(): array
    {
        return ['updated'];
    }
}
