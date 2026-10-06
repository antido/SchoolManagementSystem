<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\RoleName;
use App\Models\ActivitySubmission;
use App\Models\Award;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\ActivityPublishedNotification;
use App\Notifications\AnnouncementNotification;
use App\Notifications\AwardReceivedNotification;
use App\Notifications\ExamResultNotification;
use App\Notifications\TeacherAssignedNotification;
use App\Notifications\WorkGradedNotification;
use App\Notifications\WorkSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classSubject = ClassSubject::factory()->create();
        $this->student = Student::factory()->create();
        Enrollment::factory()->for($this->student)->create(['school_class_id' => $this->classSubject->school_class_id]);
    }

    private function teacherUser(): User
    {
        return $this->classSubject->teacher->user;
    }

    public function test_students_in_the_class_hear_about_a_newly_published_activity(): void
    {
        Notification::fake();
        $outsider = Student::factory()->create();
        $activity = LearningActivity::factory()->for($this->classSubject)->create(['title' => 'Essay']);

        $this->actingAs($this->teacherUser())->patch(route('activities.status', $activity), ['status' => 'published']);

        Notification::assertSentTo($this->student->user, ActivityPublishedNotification::class, function ($notification) {
            $data = $notification->toArray($this->student->user);

            return $data['title'] === 'New assignment: Essay' && str_starts_with($data['url'], '/activities/');
        });
        Notification::assertNotSentTo($outsider->user, ActivityPublishedNotification::class);

        // Closing and reopening does not announce it again.
        $this->actingAs($this->teacherUser())->patch(route('activities.status', $activity), ['status' => 'closed']);
        $this->actingAs($this->teacherUser())->patch(route('activities.status', $activity), ['status' => 'published']);
        Notification::assertSentToTimes($this->student->user, ActivityPublishedNotification::class, 1);
    }

    public function test_the_teacher_hears_about_submissions_and_the_student_about_grades(): void
    {
        Notification::fake();
        $activity = LearningActivity::factory()->for($this->classSubject)->published()->create();

        $this->actingAs($this->student->user)->post(route('submissions.store', $activity), ['body' => 'Done']);
        Notification::assertSentTo($this->teacherUser(), WorkSubmittedNotification::class);

        $submission = ActivitySubmission::sole();
        $this->actingAs($this->teacherUser())->patch(route('submissions.grade', $submission), ['score' => 90]);
        Notification::assertSentTo($this->student->user, WorkGradedNotification::class, fn ($notification) => $notification->toArray($this->student->user)['body'] === 'Score: 90 / 100');
    }

    public function test_students_hear_when_their_exam_result_is_ready(): void
    {
        Notification::fake();
        $quiz = LearningActivity::factory()->for($this->classSubject)->ofType(ActivityType::Quiz)->published()->create();
        $mc = Question::factory()->for($quiz)->multipleChoice()->create();
        $essay = Question::factory()->for($quiz)->shortAnswer()->create();

        $this->actingAs($this->student->user)->post(route('attempts.store', $quiz));
        $attempt = $quiz->attempts()->sole();
        $this->actingAs($this->student->user)->putJson(route('attempts.answer', $attempt), ['question_id' => $essay->id, 'answer_text' => 'Answer']);
        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        // The essay still needs the teacher, so no result yet.
        Notification::assertNotSentTo($this->student->user, ExamResultNotification::class);

        $answer = $attempt->answers()->where('question_id', $essay->id)->sole();
        $this->actingAs($this->teacherUser())->patch(route('attempts.grade', $attempt), ['scores' => [$answer->id => ['points' => 3]]]);

        Notification::assertSentToTimes($this->student->user, ExamResultNotification::class, 1);
        $this->assertNotNull($mc);
    }

    public function test_students_hear_about_awards_and_teachers_about_new_assignments(): void
    {
        Notification::fake();
        $admin = User::factory()->withRole(RoleName::Admin)->create();

        $this->actingAs($admin)->post(route('awards.grant', Award::factory()->create()), [
            'student_id' => $this->student->id,
            'awarded_on' => today()->toDateString(),
        ]);
        Notification::assertSentTo($this->student->user, AwardReceivedNotification::class);

        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->create();
        $this->actingAs($admin)->post(route('class-teachers.store', $class), ['teacher_id' => $teacher->id, 'role' => 'adviser']);
        Notification::assertSentTo($teacher->user, TeacherAssignedNotification::class, fn ($notification) => str_contains($notification->toArray($teacher->user)['title'], 'Class adviser'));

        $other = Teacher::factory()->create();
        $this->actingAs($admin)->put(route('class-subjects.update', $this->classSubject), ['teacher_id' => $other->id, 'status' => 'active']);
        Notification::assertSentTo($other->user, TeacherAssignedNotification::class);
    }

    public function test_notifications_persist_and_can_be_read_one_by_one_or_all_at_once(): void
    {
        $activity = LearningActivity::factory()->for($this->classSubject)->create();
        $this->actingAs($this->teacherUser())->patch(route('activities.status', $activity), ['status' => 'published']);
        $this->student->user->notify(new AnnouncementNotification('Holiday', 'No classes on Friday.', 'Ada Admin'));

        $user = $this->student->user->fresh();
        $this->assertSame(2, $user->unreadNotifications()->count());

        // Shared with every page for the bell.
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.unread_notifications', 2));

        $this->actingAs($user)
            ->getJson(route('notifications.recent'))
            ->assertOk()
            ->assertJsonPath('unread', 2)
            ->assertJsonCount(2, 'notifications');

        // Opening one marks it read and goes to the related page.
        $notification = $user->notifications()->where('type', ActivityPublishedNotification::class)->sole();
        $this->actingAs($user)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('activities.show', $activity, false));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertInertia(fn ($page) => $page->component('Notifications/Index')->has('notifications.data', 1));

        $this->actingAs($user)->post(route('notifications.read-all'));
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_people_cannot_touch_someone_elses_notifications(): void
    {
        $this->student->user->notify(new AnnouncementNotification('Hi', 'Hello', 'Admin'));
        $notification = $this->student->user->notifications()->sole();
        $intruder = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($intruder)->get(route('notifications.open', $notification->id))->assertNotFound();
        $this->actingAs($intruder)->patch(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_opening_never_redirects_to_another_site(): void
    {
        $user = $this->student->user;
        $user->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => AnnouncementNotification::class,
            'data' => ['title' => 'Bad', 'url' => '//evil.example.com/phish'],
        ]);

        $this->actingAs($user)
            ->get(route('notifications.open', $user->notifications()->sole()->id))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_admins_send_announcements_to_a_class(): void
    {
        Notification::fake();
        $adviser = Teacher::factory()->create();
        ClassTeacher::factory()->for($this->classSubject->schoolClass)->for($adviser)->create();
        $elsewhere = Student::factory()->create();
        Enrollment::factory()->for($elsewhere)->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->post(route('notifications.send'), [
                'audience' => 'class',
                'school_class_id' => $this->classSubject->school_class_id,
                'title' => 'Field trip',
                'message' => 'Bring a packed lunch.',
            ])
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'The announcement is being sent to 3 people.');

        Notification::assertSentTo([$this->student->user, $adviser->user, $this->teacherUser()], AnnouncementNotification::class);
        Notification::assertNotSentTo($elsewhere->user, AnnouncementNotification::class);
    }

    public function test_announcements_to_a_role_reach_only_active_accounts_with_it(): void
    {
        Notification::fake();
        $inactive = User::factory()->withRole(RoleName::Hr)->inactive()->create();
        $hr = User::factory()->withRole(RoleName::Hr)->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())->post(route('notifications.send'), [
            'audience' => 'role',
            'role' => 'hr',
            'title' => 'Payroll',
            'message' => 'Forms are due.',
        ]);

        Notification::assertSentTo($hr, AnnouncementNotification::class);
        Notification::assertNotSentTo($inactive, AnnouncementNotification::class);
    }

    public function test_only_staff_with_permission_send_announcements(): void
    {
        $this->actingAs($this->teacherUser())->get(route('notifications.announce'))->assertForbidden();
        $this->actingAs($this->student->user)->post(route('notifications.send'), [
            'audience' => 'everyone', 'title' => 'X', 'message' => 'Y',
        ])->assertForbidden();
    }
}
