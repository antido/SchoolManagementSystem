<?php

namespace Tests\Feature;

use App\Enums\ClassTeacherRole;
use App\Enums\RoleName;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->withRole(RoleName::Hr)->create();
    }

    public function test_hr_can_assign_a_teacher_to_a_class(): void
    {
        $class = SchoolClass::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->hr())
            ->post("/classes/{$class->id}/teachers", ['teacher_id' => $teacher->id, 'role' => 'adviser'])
            ->assertSessionHasNoErrors();

        $assignment = ClassTeacher::first();

        $this->assertSame(ClassTeacherRole::Adviser, $assignment->role);
        $this->assertSame($class->academic_year_id, $assignment->academic_year_id);
        $this->assertTrue($assignment->isCurrent());
    }

    public function test_the_same_teacher_cannot_be_assigned_twice(): void
    {
        $assignment = ClassTeacher::factory()->create();

        $this->actingAs($this->hr())
            ->post("/classes/{$assignment->school_class_id}/teachers", ['teacher_id' => $assignment->teacher_id, 'role' => 'teacher'])
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, ClassTeacher::count());
    }

    public function test_a_class_has_at_most_one_current_adviser(): void
    {
        $assignment = ClassTeacher::factory()->adviser()->create();

        $this->actingAs($this->hr())
            ->post("/classes/{$assignment->school_class_id}/teachers", ['teacher_id' => Teacher::factory()->create()->id, 'role' => 'adviser'])
            ->assertSessionHasErrors('role');
    }

    public function test_ending_an_assignment_keeps_it_as_history(): void
    {
        $assignment = ClassTeacher::factory()->adviser()->create();
        $hr = $this->hr();

        $this->actingAs($hr)->patch("/class-teachers/{$assignment->id}/end")->assertSessionHas('success');

        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertSame(1, ClassTeacher::count());

        // A new adviser can now be assigned.
        $this->actingAs($hr)
            ->post("/classes/{$assignment->school_class_id}/teachers", ['teacher_id' => Teacher::factory()->create()->id, 'role' => 'adviser'])
            ->assertSessionHasNoErrors();
    }

    public function test_class_page_lists_current_past_and_subject_teachers(): void
    {
        $class = SchoolClass::factory()->create();
        ClassTeacher::factory()->for($class)->adviser()->create();
        ClassTeacher::factory()->for($class)->ended()->create();
        ClassSubject::factory()->create(['school_class_id' => $class->id]);

        $this->actingAs($this->hr())
            ->get("/classes/{$class->id}")
            ->assertInertia(fn ($page) => $page
                ->has('teachers.current', 1)
                ->has('teachers.past', 1)
                ->has('teachers.subject_teachers', 1)
            );
    }

    public function test_teacher_profile_lists_their_classes_and_subjects(): void
    {
        $teacher = Teacher::factory()->create();
        ClassTeacher::factory()->for($teacher)->create();
        ClassTeacher::factory()->for($teacher)->ended()->create();
        ClassSubject::factory()->for($teacher)->create();

        $this->actingAs($this->hr())
            ->get("/teachers/{$teacher->id}")
            ->assertInertia(fn ($page) => $page->has('assignments', 2)->has('classSubjects', 1));
    }

    public function test_teachers_see_only_their_own_classes(): void
    {
        $teacher = Teacher::factory()->create();
        $adviserOf = SchoolClass::factory()->create(['name' => 'Advised']);
        $teachesIn = SchoolClass::factory()->create(['name' => 'Taught']);
        $pastClass = SchoolClass::factory()->create(['name' => 'Past']);
        $other = SchoolClass::factory()->create(['name' => 'Other']);
        ClassTeacher::factory()->for($adviserOf)->for($teacher)->adviser()->create();
        ClassSubject::factory()->create(['school_class_id' => $teachesIn->id, 'teacher_id' => $teacher->id]);
        ClassTeacher::factory()->for($pastClass)->for($teacher)->ended()->create();

        $this->actingAs($teacher->user)
            ->get('/classes?academic_year_id=')
            ->assertInertia(fn ($page) => $page->has('classes.data', 3));

        $this->actingAs($teacher->user)->get("/classes/{$adviserOf->id}")->assertOk();
        $this->actingAs($teacher->user)->get("/classes/{$pastClass->id}")->assertOk();
        $this->actingAs($teacher->user)->get("/classes/{$other->id}")->assertForbidden();
    }

    public function test_teachers_see_only_students_in_their_classes(): void
    {
        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->create();
        ClassTeacher::factory()->for($class)->for($teacher)->create();
        $mine = Student::factory()->create(['last_name' => 'Mine']);
        Enrollment::factory()->for($class)->for($mine)->create();
        $other = Student::factory()->create(['last_name' => 'Other']);

        $this->actingAs($teacher->user)
            ->get('/students')
            ->assertInertia(fn ($page) => $page->has('students.data', 1)->where('students.data.0.full_name', $mine->full_name));

        $this->actingAs($teacher->user)->get("/students/{$mine->id}")->assertOk();
        $this->actingAs($teacher->user)->get("/students/{$other->id}")->assertForbidden();
    }

    public function test_students_see_only_their_own_classes(): void
    {
        $student = Student::factory()->create();
        $mine = SchoolClass::factory()->create();
        $other = SchoolClass::factory()->create();
        Enrollment::factory()->for($mine)->for($student)->create();

        $this->actingAs($student->user)
            ->get('/classes?academic_year_id=')
            ->assertInertia(fn ($page) => $page->has('classes.data', 1));

        $this->actingAs($student->user)->get("/classes/{$mine->id}")->assertOk();
        $this->actingAs($student->user)->get("/classes/{$other->id}")->assertForbidden();
    }

    public function test_a_teacher_cannot_open_another_teachers_class_subject(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $stranger = Teacher::factory()->create();

        $this->actingAs($classSubject->teacher->user)->get("/class-subjects/{$classSubject->id}")->assertOk();
        $this->actingAs($stranger->user)->get("/class-subjects/{$classSubject->id}")->assertForbidden();
    }

    public function test_admins_still_see_every_class(): void
    {
        SchoolClass::factory()->count(3)->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->get('/classes?academic_year_id=')
            ->assertInertia(fn ($page) => $page->has('classes.data', 3));
    }

    public function test_teachers_with_history_are_kept(): void
    {
        $assignment = ClassTeacher::factory()->create();

        $this->actingAs($this->hr())->delete("/teachers/{$assignment->teacher_id}")->assertSessionHas('error');

        $this->assertNotNull($assignment->teacher->fresh());
    }

    public function test_teachers_and_students_cannot_assign_teachers(): void
    {
        $class = SchoolClass::factory()->create();
        $assignment = ClassTeacher::factory()->for($class)->create();
        $teacher = Teacher::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)
                ->post("/classes/{$class->id}/teachers", ['teacher_id' => $teacher->id, 'role' => 'teacher'])
                ->assertForbidden();
            $this->actingAs($user)->patch("/class-teachers/{$assignment->id}/end")->assertForbidden();
        }

        $this->assertSame(1, ClassTeacher::count());
        $this->assertTrue($assignment->fresh()->isCurrent());
    }
}
