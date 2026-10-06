<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\RoleName;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LearningActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'assignment',
            'title' => 'Fractions worksheet',
            'description' => 'Practice adding fractions.',
            'instructions' => 'Answer all ten items.',
            'start_at' => '',
            'due_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => '',
            'total_points' => '20',
            ...$overrides,
        ];
    }

    public function test_a_teacher_creates_a_draft_for_a_subject_they_teach(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $teacher = $classSubject->teacher;

        $this->actingAs($teacher->user)
            ->get(route('activities.create', ['class_subject' => $classSubject->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Activities/Create')
                ->has('classSubjects', 1)
                ->where('defaults.class_subject_id', $classSubject->id)
            );

        $this->actingAs($teacher->user)
            ->post(route('activities.store'), $this->payload([
                'class_subject_id' => $classSubject->id,
                'due_at' => '2030-01-15T09:30:00+08:00',
            ]))
            ->assertRedirect();

        $activity = LearningActivity::sole();
        $this->assertSame(ActivityStatus::Draft, $activity->status);
        $this->assertSame($teacher->id, $activity->teacher_id);
        $this->assertSame('2030-01-15 01:30:00', $activity->due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_teacher_cannot_create_activities_for_someone_elses_subject(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $other = Teacher::factory()->create();

        $this->actingAs($other->user)
            ->post(route('activities.store'), $this->payload(['class_subject_id' => $classSubject->id]))
            ->assertForbidden();

        $this->actingAs($other->user)
            ->get(route('activities.create'))
            ->assertInertia(fn ($page) => $page->has('classSubjects', 0));

        $this->assertDatabaseCount('learning_activities', 0);
    }

    public function test_admins_can_create_activities_for_any_class_subject(): void
    {
        $classSubject = ClassSubject::factory()->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->post(route('activities.store'), $this->payload(['class_subject_id' => $classSubject->id, 'type' => 'examination']))
            ->assertRedirect();

        $this->assertSame($classSubject->teacher_id, LearningActivity::sole()->teacher_id);
    }

    public function test_students_cannot_create_activities(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->post(route('activities.store'), $this->payload(['class_subject_id' => $classSubject->id]))
            ->assertForbidden();
    }

    public function test_each_kind_needs_its_own_permission(): void
    {
        Role::findByName(RoleName::Teacher->value)->revokePermissionTo('examinations.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $classSubject = ClassSubject::factory()->create();

        $this->actingAs($classSubject->teacher->user)
            ->post(route('activities.store'), $this->payload(['class_subject_id' => $classSubject->id, 'type' => 'quiz']))
            ->assertForbidden();

        $this->actingAs($classSubject->teacher->user)
            ->get(route('activities.create'))
            ->assertInertia(fn ($page) => $page->where('types', fn ($types) => collect($types)->pluck('value')->doesntContain('quiz')));
    }

    public function test_inactive_class_subjects_cannot_get_new_activities(): void
    {
        $classSubject = ClassSubject::factory()->create(['status' => 'inactive']);

        $this->actingAs($classSubject->teacher->user)
            ->post(route('activities.store'), $this->payload(['class_subject_id' => $classSubject->id]))
            ->assertForbidden();
    }

    public function test_the_due_date_must_be_after_the_start_date(): void
    {
        $classSubject = ClassSubject::factory()->create();

        $this->actingAs($classSubject->teacher->user)
            ->post(route('activities.store'), $this->payload([
                'class_subject_id' => $classSubject->id,
                'start_at' => now()->addDays(5)->toIso8601String(),
                'due_at' => now()->addDays(2)->toIso8601String(),
            ]))
            ->assertSessionHasErrors('due_at');
    }

    public function test_the_class_subject_cannot_change_when_editing(): void
    {
        $activity = LearningActivity::factory()->create();
        $otherSubject = ClassSubject::factory()->create(['teacher_id' => $activity->classSubject->teacher_id]);

        $this->actingAs($activity->classSubject->teacher->user)
            ->put(route('activities.update', $activity), $this->payload(['class_subject_id' => $otherSubject->id]))
            ->assertSessionHasErrors('class_subject_id');

        $this->actingAs($activity->classSubject->teacher->user)
            ->put(route('activities.update', $activity), $this->payload(['title' => 'Renamed']))
            ->assertRedirect(route('activities.show', $activity));

        $this->assertSame('Renamed', $activity->fresh()->title);
    }

    public function test_activities_move_through_draft_published_and_closed(): void
    {
        $activity = LearningActivity::factory()->create();
        $user = $activity->classSubject->teacher->user;

        // A draft cannot be closed directly.
        $this->actingAs($user)->patch(route('activities.status', $activity), ['status' => 'closed'])->assertForbidden();

        $this->actingAs($user)->patch(route('activities.status', $activity), ['status' => 'published'])->assertSessionHas('success');
        $this->assertSame(ActivityStatus::Published, $activity->fresh()->status);

        $this->actingAs($user)->patch(route('activities.status', $activity), ['status' => 'closed']);
        $this->assertSame(ActivityStatus::Closed, $activity->fresh()->status);

        // Reopening.
        $this->actingAs($user)->patch(route('activities.status', $activity), ['status' => 'published']);
        $this->assertSame(ActivityStatus::Published, $activity->fresh()->status);
    }

    public function test_an_activity_past_its_due_date_cannot_be_published(): void
    {
        $activity = LearningActivity::factory()->create(['due_at' => now()->subDay()]);

        $this->actingAs($activity->classSubject->teacher->user)
            ->patch(route('activities.status', $activity), ['status' => 'published'])
            ->assertSessionHas('error');

        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);
    }

    public function test_other_teachers_cannot_manage_an_activity(): void
    {
        $activity = LearningActivity::factory()->create();
        $other = Teacher::factory()->create();

        $this->actingAs($other->user)->get(route('activities.show', $activity))->assertForbidden();
        $this->actingAs($other->user)->put(route('activities.update', $activity), $this->payload())->assertForbidden();
        $this->actingAs($other->user)->patch(route('activities.status', $activity), ['status' => 'published'])->assertForbidden();
        $this->actingAs($other->user)->delete(route('activities.destroy', $activity))->assertForbidden();
    }

    public function test_teachers_only_list_activities_for_their_subjects(): void
    {
        $mine = LearningActivity::factory()->create();
        LearningActivity::factory()->create();

        $this->actingAs($mine->classSubject->teacher->user)
            ->get(route('activities.index'))
            ->assertInertia(fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $mine->id)
                ->where('can.create', true)
            );
    }

    public function test_students_only_see_available_activities_in_their_current_classes(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $classSubject->school_class_id]);

        $visible = LearningActivity::factory()->for($classSubject)->published()->create();
        $closed = LearningActivity::factory()->for($classSubject)->closed()->create();
        $draft = LearningActivity::factory()->for($classSubject)->create();
        $notOpenYet = LearningActivity::factory()->for($classSubject)->published()->create(['start_at' => now()->addDay()]);
        $otherClass = LearningActivity::factory()->published()->create();

        $this->actingAs($student->user)
            ->get(route('activities.index'))
            ->assertInertia(fn ($page) => $page
                ->has('activities.data', 2)
                ->where('statuses', [])
                ->where('can.create', false)
            );

        $this->actingAs($student->user)->get(route('activities.show', $visible))->assertOk();
        $this->actingAs($student->user)->get(route('activities.show', $closed))->assertOk();

        foreach ([$draft, $notOpenYet, $otherClass] as $hidden) {
            $this->actingAs($student->user)->get(route('activities.show', $hidden))->assertForbidden();
        }
    }

    public function test_withdrawn_students_no_longer_see_the_class_activities(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->withdrawn()->create(['school_class_id' => $classSubject->school_class_id]);
        $activity = LearningActivity::factory()->for($classSubject)->published()->create();

        $this->actingAs($student->user)->get(route('activities.show', $activity))->assertForbidden();
    }

    public function test_availability_reflects_the_schedule(): void
    {
        $this->assertSame('draft', LearningActivity::factory()->create()->availability());
        $this->assertSame('open', LearningActivity::factory()->published()->create()->availability());
        $this->assertSame('scheduled', LearningActivity::factory()->published()->create(['start_at' => now()->addHour()])->availability());
        $this->assertSame('past_due', LearningActivity::factory()->published()->create(['due_at' => now()->subHour()])->availability());
        $this->assertSame('closed', LearningActivity::factory()->closed()->create()->availability());
    }

    public function test_a_teacher_deletes_an_activity_and_class_subjects_with_activities_are_kept(): void
    {
        $activity = LearningActivity::factory()->create();
        $classSubject = $activity->classSubject;

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->delete(route('class-subjects.destroy', $classSubject))
            ->assertSessionHas('error');

        $this->actingAs($classSubject->teacher->user)
            ->delete(route('activities.destroy', $activity))
            ->assertRedirect(route('activities.index'));

        $this->assertModelMissing($activity);
    }

    public function test_the_class_subject_page_lists_its_activities(): void
    {
        $activity = LearningActivity::factory()->ofType(ActivityType::Quiz)->create();

        $this->actingAs($activity->classSubject->teacher->user)
            ->get(route('class-subjects.show', $activity->classSubject))
            ->assertInertia(fn ($page) => $page
                ->has('activities', 1)
                ->where('activities.0.type', 'quiz')
                ->where('can.createActivity', true)
            );
    }

    public function test_dashboards_show_upcoming_activities(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $classSubject->school_class_id]);
        LearningActivity::factory()->for($classSubject)->published()->create(['title' => 'Due soon']);
        LearningActivity::factory()->for($classSubject)->published()->create(['due_at' => now()->subDay()]);

        $this->actingAs($student->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('upcoming', 1)->where('upcoming.0.title', 'Due soon'));
    }
}
