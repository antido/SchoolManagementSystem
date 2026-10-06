<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accounts given the teacher or student role before their teacher or
 * student profile exists must not see the whole school.
 */
class ProfilelessAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_account_without_a_profile_sees_no_classes_students_or_activities(): void
    {
        $user = User::factory()->withRole(RoleName::Teacher)->create();
        $class = SchoolClass::factory()->create();
        Student::factory()->create();
        ClassSubject::factory()->withoutTeacher()->create();
        $activity = LearningActivity::factory()->published()->create();

        $this->assertTrue($user->isLimitedToOwnClasses());

        $this->actingAs($user)->get(route('classes.index'))->assertInertia(fn ($page) => $page->has('classes.data', 0));
        $this->actingAs($user)->get(route('classes.show', $class))->assertForbidden();
        $this->actingAs($user)->get(route('students.index'))->assertInertia(fn ($page) => $page->has('students.data', 0));
        $this->actingAs($user)->get(route('activities.index'))->assertInertia(fn ($page) => $page->has('activities.data', 0)->where('can.create', false));
        $this->actingAs($user)->get(route('activities.show', $activity))->assertForbidden();
        $this->actingAs($user)->get(route('grading.index'))->assertForbidden();
    }

    public function test_a_student_account_without_a_profile_sees_nothing(): void
    {
        $user = User::factory()->withRole(RoleName::Student)->create();
        SchoolClass::factory()->create();
        LearningActivity::factory()->published()->create();

        $this->actingAs($user)->get(route('classes.index'))->assertInertia(fn ($page) => $page->has('classes.data', 0));
        $this->actingAs($user)->get(route('activities.index'))->assertInertia(fn ($page) => $page->has('activities.data', 0));
    }
}
