<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostReport;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumReplyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->withRole(RoleName::Student)->create();
    }

    private function teacher(): User
    {
        return User::factory()->withRole(RoleName::Teacher)->create();
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    private function topicBy(User $author, array $attributes = []): ForumTopic
    {
        return ForumTopic::factory()->create(['user_id' => $author->id, ...$attributes]);
    }

    public function test_students_start_topics_and_reply(): void
    {
        $category = ForumCategory::factory()->create();
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('forum.topics.store', $category), ['title' => 'Help with fractions', 'body' => 'How do I add 1/2 and 1/3?'])
            ->assertRedirect();

        $topic = ForumTopic::sole();
        $this->assertSame('How do I add 1/2 and 1/3?', $topic->firstPost->body);

        $classmate = $this->student();
        $this->actingAs($classmate)->post(route('forum.topics.reply', $topic), ['body' => 'Find a common denominator.'])->assertRedirect();

        $topic->refresh();
        $this->assertSame(1, $topic->replies_count);
        $this->assertSame(2, $topic->posts()->count());

        $this->actingAs($student)
            ->get(route('forum.topics.show', $topic))
            ->assertInertia(fn ($page) => $page
                ->component('Forum/Topic')
                ->has('posts.data', 2)
                ->where('posts.data.0.is_opening', true)
                ->where('can.reply', true)
                ->where('can.moderate', false)
            );
    }

    public function test_the_topic_author_hears_about_replies_but_not_their_own(): void
    {
        Notification::fake();
        $author = $this->student();
        $topic = $this->topicBy($author);

        $this->actingAs($author)->post(route('forum.topics.reply', $topic), ['body' => 'Bump']);
        Notification::assertNothingSentTo($author);

        $this->actingAs($this->student())->post(route('forum.topics.reply', $topic), ['body' => 'Answer']);
        Notification::assertSentTo($author, ForumReplyNotification::class);
    }

    public function test_authors_edit_their_own_posts_only(): void
    {
        $author = $this->student();
        $topic = $this->topicBy($author);
        $post = $topic->firstPost;

        $this->actingAs($this->student())->put(route('forum.posts.update', $post), ['body' => 'Hacked'])->assertForbidden();
        // Moderators remove posts but do not rewrite them.
        $this->actingAs($this->teacher())->put(route('forum.posts.update', $post), ['body' => 'Rewritten'])->assertForbidden();

        $this->actingAs($author)->put(route('forum.posts.update', $post), ['body' => 'Corrected'])->assertSessionHas('success');
        $this->assertSame('Corrected', $post->fresh()->body);
        $this->assertNotNull($post->fresh()->edited_at);
    }

    public function test_locked_topics_take_replies_from_moderators_only_and_cannot_be_edited(): void
    {
        $author = $this->student();
        $topic = $this->topicBy($author, ['is_locked' => true]);

        $this->actingAs($author)->post(route('forum.topics.reply', $topic), ['body' => 'Late'])->assertForbidden();
        $this->actingAs($author)->put(route('forum.posts.update', $topic->firstPost), ['body' => 'Changed'])->assertForbidden();
        $this->actingAs($this->teacher())->post(route('forum.topics.reply', $topic), ['body' => 'Closing note'])->assertRedirect();
    }

    public function test_reactions_toggle(): void
    {
        $topic = $this->topicBy($this->student());
        $user = $this->student();
        $post = $topic->firstPost;

        $this->actingAs($user)->post(route('forum.posts.react', $post), ['type' => 'helpful']);
        $this->assertSame(1, $post->reactions()->count());

        $this->actingAs($user)
            ->get(route('forum.topics.show', $topic))
            ->assertInertia(fn ($page) => $page->where('posts.data.0.reactions.helpful', ['count' => 1, 'mine' => true]));

        $this->actingAs($user)->post(route('forum.posts.react', $post), ['type' => 'helpful']);
        $this->assertSame(0, $post->reactions()->count());

        $this->actingAs($user)->post(route('forum.posts.react', $post), ['type' => 'angry'])->assertSessionHasErrors('type');
    }

    public function test_posts_are_reported_once_and_not_by_their_author(): void
    {
        $author = $this->student();
        $post = $this->topicBy($author)->firstPost;
        $reporter = $this->student();

        $this->actingAs($author)->post(route('forum.posts.report', $post), ['reason' => 'Mine'])->assertForbidden();

        $this->actingAs($reporter)->post(route('forum.posts.report', $post), ['reason' => 'Rude language'])->assertSessionHas('success');
        $this->actingAs($reporter)->post(route('forum.posts.report', $post), ['reason' => 'Again'])->assertForbidden();

        $this->assertSame(1, ForumPostReport::count());
    }

    public function test_moderators_remove_reported_posts_and_the_reports_close(): void
    {
        $topic = $this->topicBy($this->student());
        $reply = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $topic->forceFill(['replies_count' => 1])->save();
        $first = ForumPostReport::query()->forceCreate(['forum_post_id' => $reply->id, 'user_id' => $this->student()->id, 'reason' => 'Spam', 'status' => 'pending']);
        $second = ForumPostReport::query()->forceCreate(['forum_post_id' => $reply->id, 'user_id' => $this->student()->id, 'reason' => 'Spam too', 'status' => 'pending']);
        $moderator = $this->teacher();

        $this->actingAs($moderator)
            ->get(route('forum.reports.index'))
            ->assertInertia(fn ($page) => $page->component('Forum/Reports')->has('reports.data', 2));

        $this->actingAs($moderator)->patch(route('forum.reports.resolve', $first), ['action' => 'remove'])->assertSessionHas('success');

        $this->assertSoftDeleted($reply);
        $this->assertSame($moderator->id, $reply->fresh()->deleted_by);
        $this->assertSame(ReportStatus::Resolved, $second->fresh()->status);
        $this->assertSame(0, $topic->fresh()->replies_count);

        // Readers see a placeholder instead of the text.
        $this->actingAs($this->student())
            ->get(route('forum.topics.show', $topic))
            ->assertInertia(fn ($page) => $page->where('posts.data.1.removed', true)->where('posts.data.1.body', null));
    }

    public function test_moderators_dismiss_reports_about_acceptable_posts(): void
    {
        $post = $this->topicBy($this->student())->firstPost;
        $report = ForumPostReport::query()->forceCreate(['forum_post_id' => $post->id, 'user_id' => $this->student()->id, 'reason' => 'I disagree', 'status' => 'pending']);

        $this->actingAs($this->teacher())->patch(route('forum.reports.resolve', $report), ['action' => 'dismiss']);

        $this->assertSame(ReportStatus::Dismissed, $report->fresh()->status);
        $this->assertNotSoftDeleted($post);
    }

    public function test_students_cannot_moderate(): void
    {
        $topic = $this->topicBy($this->student());
        $student = $this->student();

        $this->actingAs($student)->get(route('forum.reports.index'))->assertForbidden();
        $this->actingAs($student)->patch(route('forum.topics.update', $topic), ['is_pinned' => true])->assertForbidden();
        $this->actingAs($student)->delete(route('forum.topics.destroy', $topic))->assertForbidden();
        $this->actingAs($student)->delete(route('forum.posts.destroy', $topic->firstPost))->assertForbidden();
    }

    public function test_moderators_pin_lock_and_remove_topics(): void
    {
        $topic = $this->topicBy($this->student());
        $moderator = $this->teacher();

        $this->actingAs($moderator)->patch(route('forum.topics.update', $topic), ['is_pinned' => true, 'is_locked' => true]);
        $this->assertTrue($topic->fresh()->is_pinned);
        $this->assertTrue($topic->fresh()->is_locked);

        $this->actingAs($moderator)->delete(route('forum.topics.destroy', $topic))->assertRedirect();
        $this->assertSoftDeleted($topic);

        // Gone for readers, still reachable for moderators.
        $this->actingAs($this->student())->get(route('forum.topics.show', $topic))->assertForbidden();
        $this->actingAs($moderator)->get(route('forum.topics.show', $topic))->assertInertia(fn ($page) => $page->where('topic.deleted', true));
    }

    public function test_authors_remove_their_own_replies_and_their_topic_only_before_anyone_replies(): void
    {
        $author = $this->student();
        $topic = $this->topicBy($author);
        $this->actingAs($author)->delete(route('forum.topics.destroy', $topic))->assertRedirect();
        $this->assertSoftDeleted($topic);

        $other = $this->topicBy($author);
        $this->actingAs($this->student())->post(route('forum.topics.reply', $other), ['body' => 'Reply']);
        $this->actingAs($author)->delete(route('forum.topics.destroy', $other->fresh()))->assertForbidden();

        $mine = ForumPost::factory()->create(['forum_topic_id' => $other->id, 'user_id' => $author->id]);
        $this->actingAs($author)->delete(route('forum.posts.destroy', $mine))->assertSessionHas('success');
        $this->assertSoftDeleted($mine);
    }

    public function test_pinned_topics_are_listed_first_and_topics_can_be_searched(): void
    {
        $category = ForumCategory::factory()->create();
        ForumTopic::factory()->for($category, 'category')->create(['title' => 'Old news', 'last_posted_at' => now()->subWeek()]);
        ForumTopic::factory()->for($category, 'category')->create(['title' => 'Rules', 'is_pinned' => true, 'last_posted_at' => now()->subMonth()]);
        ForumTopic::factory()->for($category, 'category')->create(['title' => 'Photosynthesis question', 'last_posted_at' => now()]);
        $user = $this->student();

        $this->actingAs($user)
            ->get(route('forum.categories.show', $category))
            ->assertInertia(fn ($page) => $page->where('topics.data.0.title', 'Rules')->where('topics.data.1.title', 'Photosynthesis question'));

        $this->actingAs($user)
            ->get(route('forum.index', ['search' => 'photosynth']))
            ->assertInertia(fn ($page) => $page->has('results.data', 1)->where('results.data.0.title', 'Photosynthesis question'));
    }

    public function test_only_administrators_manage_categories(): void
    {
        $this->actingAs($this->teacher())->post(route('forum.categories.store'), ['name' => 'General'])->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('forum.categories.store'), ['name' => 'General', 'description' => 'Anything'])->assertSessionHas('success');
        $category = ForumCategory::sole();
        $this->assertSame(0, $category->order);

        $this->topicBy($this->student(), ['forum_category_id' => $category->id]);
        $this->actingAs($admin)->delete(route('forum.categories.destroy', $category))->assertSessionHas('error');
        $this->assertModelExists($category);
    }

    public function test_people_without_forum_access_are_kept_out(): void
    {
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get(route('forum.index'))->assertForbidden();
        $this->actingAs($nobody)->post(route('forum.topics.store', ForumCategory::factory()->create()), ['title' => 'X', 'body' => 'Y'])->assertForbidden();
    }
}
