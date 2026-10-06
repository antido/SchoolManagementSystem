<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_saved_and_broadcast_only_when_realtime_is_on(): void
    {
        $user = User::factory()->create();
        $notification = new AnnouncementNotification('Fire drill', 'At 10:00.', 'Admin');

        $this->assertSame(['database'], $notification->via($user));

        config(['school.realtime' => true]);
        $this->assertSame(['database', 'broadcast'], $notification->via($user));
        $this->assertSame(['title' => 'Fire drill'], $notification->toBroadcast($user)->data);
    }

    public function test_pages_know_whether_to_listen_or_poll(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('app.realtime', false));

        config(['school.realtime' => true]);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('app.realtime', true));
    }

    public function test_people_may_only_listen_on_their_own_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // The test default ("null") approves every channel; check with the real
        // Reverb broadcaster (dummy keys, nothing is sent) and its channel rules.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->actingAs($user)
            ->post('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$user->id}", 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($user)
            ->post('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$other->id}", 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    public function test_the_unread_count_can_be_refreshed_alone(): void
    {
        $user = User::factory()->create();
        $user->notify(new AnnouncementNotification('One', 'Body', 'Admin'));

        // What the page asks for when told something new arrived (an Inertia partial reload).
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->reloadOnly('auth', fn ($reload) => $reload->where('auth.unread_notifications', 1)));
    }
}
