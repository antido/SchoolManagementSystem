<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\Faq;
use App\Models\ForumPostReport;
use App\Models\ForumTopic;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentGrade;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_database_seeds_a_complete_demo_school(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, SchoolClass::count());
        $this->assertGreaterThanOrEqual(100, Student::count());
        $this->assertGreaterThan(0, LearningActivity::where('status', ActivityStatus::Draft)->count());
        $this->assertGreaterThan(0, LearningActivity::where('status', ActivityStatus::Published)->count());
        $this->assertGreaterThan(0, LearningActivity::where('status', ActivityStatus::Closed)->count());
        $this->assertGreaterThan(0, ActivitySubmission::where('is_late', true)->count());
        $this->assertGreaterThan(0, ActivityAttempt::count());
        $this->assertGreaterThan(1000, Attendance::count());
        $this->assertGreaterThan(0, StudentGrade::count());
        $this->assertGreaterThan(0, StudentAward::count());
        $this->assertGreaterThan(0, ForumTopic::where('is_pinned', true)->count());
        $this->assertGreaterThan(0, ForumPostReport::count());
        $this->assertGreaterThan(0, Faq::where('is_published', false)->count());
    }

    public function test_every_demo_account_can_sign_in_and_sees_its_own_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (DemoAccountsSeeder::ACCOUNTS as $role => $account) {
            $this->post('/login', ['email' => $account['email'], 'password' => 'password'])->assertRedirect('/dashboard');
            $this->get('/dashboard')->assertOk();
            $this->post('/logout');
        }

        $teacher = User::firstWhere('email', DemoAccountsSeeder::ACCOUNTS['teacher']['email']);
        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('type', 'teacher')->has('classes', 2));

        $student = User::firstWhere('email', DemoAccountsSeeder::ACCOUNTS['student']['email']);
        $this->actingAs($student)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('type', 'student')->has('classes', 1)->has('upcoming'));
        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertInertia(fn ($page) => $page->has('grades', 6));
    }

    public function test_seeding_twice_does_not_duplicate_the_school(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, SchoolClass::count());
    }
}
