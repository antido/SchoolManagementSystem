<?php

namespace App\Services;

use App\Enums\ReactionType;
use App\Enums\ReportStatus;
use App\Events\ForumReplied;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumPostReport;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Topics, replies, reactions, reports and moderation.
 *
 * Removing a topic or post soft-deletes it and records who removed it, so
 * moderators keep a trail; readers see "removed" in its place.
 */
class ForumService
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function createTopic(ForumCategory $category, User $author, string $title, string $body, array $files = []): ForumTopic
    {
        return DB::transaction(function () use ($category, $author, $title, $body, $files) {
            $topic = new ForumTopic;
            $topic->forum_category_id = $category->id;
            $topic->user_id = $author->id;
            $topic->title = $title;
            $topic->last_posted_at = now();
            $topic->save();

            $this->newPost($topic, $author, $body, $files);

            return $topic;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function reply(ForumTopic $topic, User $author, string $body, array $files = []): ForumPost
    {
        $post = DB::transaction(function () use ($topic, $author, $body, $files) {
            $post = $this->newPost($topic, $author, $body, $files);

            $topic->increment('replies_count', 1, ['last_posted_at' => now()]);

            return $post;
        });

        ForumReplied::dispatch($post);

        return $post;
    }

    public function editPost(ForumPost $post, string $body): void
    {
        $post->body = $body;
        $post->edited_at = now();
        $post->save();
    }

    /**
     * Remove a reply (or, for the opening post, the whole topic).
     */
    public function removePost(ForumPost $post, User $by): void
    {
        DB::transaction(function () use ($post, $by): void {
            if ($post->isOpening()) {
                $this->removeTopic($post->topic, $by);

                return;
            }

            $post->deleted_by = $by->id;
            $post->save();
            $post->delete();

            $post->topic->decrement('replies_count');
            $this->closeReports($post, ReportStatus::Resolved, $by);
        });
    }

    public function removeTopic(ForumTopic $topic, User $by): void
    {
        $topic->deleted_by = $by->id;
        $topic->save();
        $topic->delete();

        ForumPost::query()->where('forum_topic_id', $topic->id)->get()
            ->each(fn (ForumPost $post) => $this->closeReports($post, ReportStatus::Resolved, $by));
    }

    public function setPinned(ForumTopic $topic, bool $pinned): void
    {
        $topic->forceFill(['is_pinned' => $pinned])->save();
    }

    public function setLocked(ForumTopic $topic, bool $locked): void
    {
        $topic->forceFill(['is_locked' => $locked])->save();
    }

    /**
     * Add the reaction, or take it back if the person already gave it.
     *
     * @return bool whether the reaction is now given
     */
    public function toggleReaction(ForumPost $post, User $user, ReactionType $type): bool
    {
        $existing = ForumPostReaction::query()
            ->where('forum_post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $reaction = new ForumPostReaction;
        $reaction->forum_post_id = $post->id;
        $reaction->user_id = $user->id;
        $reaction->type = $type;
        $reaction->save();

        return true;
    }

    public function report(ForumPost $post, User $reporter, string $reason): ForumPostReport
    {
        $report = new ForumPostReport;
        $report->forum_post_id = $post->id;
        $report->user_id = $reporter->id;
        $report->reason = $reason;
        $report->status = ReportStatus::Pending;
        $report->save();

        return $report;
    }

    /**
     * A moderator's decision on a report: remove the post (closing every
     * report about it) or dismiss the report.
     */
    public function resolveReport(ForumPostReport $report, bool $removePost, User $moderator): void
    {
        if ($removePost) {
            if (! $report->post->trashed()) {
                $this->removePost($report->post, $moderator);
            }

            $this->closeReports($report->post, ReportStatus::Resolved, $moderator);

            return;
        }

        $report->status = ReportStatus::Dismissed;
        $report->resolved_by = $moderator->id;
        $report->resolved_at = now();
        $report->save();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function newPost(ForumTopic $topic, User $author, string $body, array $files = []): ForumPost
    {
        $post = new ForumPost;
        $post->forum_topic_id = $topic->id;
        $post->user_id = $author->id;
        $post->body = $body;
        $post->save();

        $this->attachments->attach($post, $files, $author, "forum/{$topic->id}");

        return $post;
    }

    private function closeReports(ForumPost $post, ReportStatus $status, User $by): void
    {
        $post->reports()->where('status', ReportStatus::Pending)->update([
            'status' => $status,
            'resolved_by' => $by->id,
            'resolved_at' => now(),
        ]);
    }
}
