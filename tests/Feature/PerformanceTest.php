<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Events\ActivityPublished;
use App\Jobs\SendAnnouncement;
use App\Listeners\SendSchoolNotifications;
use App\Models\ClassSubject;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcements_are_delivered_on_the_queue(): void
    {
        Queue::fake();
        User::factory()->count(3)->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->post(route('notifications.send'), ['audience' => 'everyone', 'title' => 'Hello', 'message' => 'School resumes Monday.'])
            ->assertSessionHas('success', 'The announcement is being sent to 4 people.');

        Queue::assertPushed(SendAnnouncement::class, fn (SendAnnouncement $job) => $job->title === 'Hello');
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_event_notifications_are_sent_from_the_queue(): void
    {
        Queue::fake();
        $this->assertContains(ShouldQueue::class, class_implements(SendSchoolNotifications::class));

        $activity = LearningActivity::factory()->create();
        $this->actingAs($activity->classSubject->teacher->user)->patch(route('activities.status', $activity), ['status' => 'published']);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === SendSchoolNotifications::class
            && $job->data[0] instanceof ActivityPublished);
    }

    public function test_slow_queries_are_logged(): void
    {
        config(['school.slow_query_ms' => 1]);
        $this->refreshApplicationWithSlowQueryLogging();

        Log::shouldReceive('warning')->atLeast()->once()->withArgs(fn (string $message, array $context) => $message === 'Slow query' && isset($context['sql']));

        // A deliberately slow query: SQLite counts a large generated series.
        DB::select('with recursive n(x) as (select 1 union all select x + 1 from n where x < 300000) select count(*) from n');
    }

    public function test_long_lists_do_not_cost_a_query_per_row(): void
    {
        $topic = ForumTopic::factory()->create();
        ForumPost::factory()->count(19)->create(['forum_topic_id' => $topic->id]);

        $class = SchoolClass::factory()->create();
        ClassSubject::factory()->count(12)->create(['school_class_id' => $class->id, 'subject_id' => fn () => Subject::factory()]);

        $admin = User::factory()->withRole(RoleName::Admin)->create();

        $count = function (string $url) use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get($url)->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertLessThan(22, $count(route('forum.topics.show', $topic)), 'The forum topic page runs a query per post.');
        $this->assertLessThan(22, $count(route('classes.show', $class)), 'The class page runs a query per subject.');
    }

    /**
     * The slow query listener is set up when the app boots; boot again so it
     * picks up the lowered threshold.
     */
    private function refreshApplicationWithSlowQueryLogging(): void
    {
        $this->app->getProvider(AppServiceProvider::class)->boot();
    }
}
