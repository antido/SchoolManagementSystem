<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\ForumTopic;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    public function test_changes_record_the_actor_and_the_values_before_and_after(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['first_name' => 'Ana']);

        $this->actingAs($admin);
        $student->update(['first_name' => 'Anna']);

        $entry = AuditLog::query()->where('action', 'student.updated')->sole();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertTrue($entry->subject->is($student));
        $this->assertSame(['first_name' => 'Ana'], $entry->old_values);
        $this->assertSame(['first_name' => 'Anna'], $entry->new_values);
        $this->assertStringContainsString('Anna', $entry->description);
    }

    public function test_secrets_and_noise_are_never_recorded(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password' => 'new-secret-password', 'last_login_at' => now()])->save();

        $created = AuditLog::query()->where('action', 'user.created')->sole();
        $this->assertArrayNotHasKey('password', $created->new_values);
        $this->assertArrayNotHasKey('remember_token', $created->new_values);

        // Only the password and sign-in time changed, and neither is recorded.
        $this->assertSame(0, AuditLog::query()->where('action', 'user.updated')->count());
    }

    public function test_the_school_events_listed_in_the_brief_are_traced(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();

        $this->post(route('enrollments.store', $classSubject->schoolClass), ['student_ids' => [$student->id]]);
        $this->post(route('class-teachers.store', $classSubject->schoolClass), ['teacher_id' => $classSubject->teacher_id, 'role' => 'adviser']);
        $this->post(route('activities.store'), ['class_subject_id' => $classSubject->id, 'type' => 'examination', 'title' => 'Midterm', 'total_points' => 50]);
        Attendance::factory()->for($student)->create(['school_class_id' => $classSubject->school_class_id]);

        $actions = AuditLog::query()->pluck('action');

        foreach (['enrollment.created', 'class_teacher.created', 'activity.created', 'attendance.created', 'student.created', 'teacher.created'] as $action) {
            $this->assertTrue($actions->contains($action), "Missing {$action}");
        }

        $this->assertSame('examination', AuditLog::query()->where('action', 'activity.created')->sole()->new_values['type']);
    }

    public function test_a_recomputed_grade_shows_the_old_and_new_percentage(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();

        foreach ([70, 85] as $percentage) {
            StudentGrade::query()->where('student_id', $student->id)->update(['superseded_at' => now()]);
            $grade = new StudentGrade;
            $grade->class_subject_id = $classSubject->id;
            $grade->student_id = $student->id;
            $grade->percentage = $percentage;
            $grade->descriptor = 'x';
            $grade->passed = $percentage >= 75;
            $grade->breakdown = [];
            $grade->computed_at = now();
            $grade->save();
        }

        $latest = AuditLog::query()->where('action', 'grade.created')->latest('id')->first();
        $this->assertEquals(70, $latest->old_values['percentage']);
        $this->assertEquals(85, $latest->new_values['percentage']);
        $this->assertArrayNotHasKey('breakdown', $latest->new_values);
    }

    public function test_forum_moderation_is_traced(): void
    {
        $topic = ForumTopic::factory()->create();
        $moderator = User::factory()->withRole(RoleName::Teacher)->create();

        $this->actingAs($moderator)->patch(route('forum.topics.update', $topic), ['is_locked' => true]);
        $this->actingAs($moderator)->delete(route('forum.topics.destroy', $topic));

        $entries = AuditLog::query()->where('subject_type', $topic->getMorphClass())->orderBy('id')->get();
        $this->assertEquals(1, $entries->firstWhere('action', 'forum_topic.updated')->new_values['is_locked']);
        $this->assertTrue($entries->pluck('action')->contains('forum_topic.deleted'));
        $this->assertTrue($entries->every(fn (AuditLog $entry) => $entry->user_id === $moderator->id));
    }

    public function test_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = app(AuditLogger::class)->record('test.created', null, 'Something happened');

        $this->expectException(LogicException::class);
        $entry->forceFill(['description' => 'Something else'])->save();
    }

    public function test_ip_addresses_are_only_kept_when_the_setting_is_on(): void
    {
        $this->actingAs($this->admin());
        Student::factory()->create();
        $this->assertNull(AuditLog::query()->latest('id')->first()->ip_address);

        config(['school.audit.store_ip' => true]);
        Student::factory()->create();
        $this->assertSame('127.0.0.1', AuditLog::query()->latest('id')->first()->ip_address);
    }

    public function test_only_administrators_can_read_the_trail_and_it_can_be_filtered(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        // A name random factory data cannot produce, so only this student matches the search.
        Student::factory()->create(['first_name' => 'Zxqvelda']);
        ForumTopic::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student, RoleName::Hr] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())->get(route('audit.index'))->assertForbidden();
        }

        $this->actingAs($admin)
            ->get(route('audit.index', ['search' => 'Zxqvelda']))
            ->assertInertia(fn ($page) => $page->component('Audit/Index')->has('entries.data', 1)->where('entries.data.0.actor', $admin->name));

        $this->actingAs($admin)
            ->get(route('audit.index', ['area' => 'student']))
            ->assertInertia(fn ($page) => $page->where('entries.data', fn ($entries) => collect($entries)->every(fn ($entry) => str_starts_with($entry['action'], 'student.'))));
    }
}
