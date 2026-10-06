<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    public function test_several_students_can_be_enrolled_at_once(): void
    {
        $class = SchoolClass::factory()->create();
        $students = Student::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => $students->pluck('id')->all()])
            ->assertSessionHas('success', '3 students enrolled.');

        $this->assertSame(3, $class->enrollments()->active()->count());

        $enrollment = Enrollment::first();
        $this->assertSame($class->academic_year_id, $enrollment->academic_year_id);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_a_student_already_in_the_class_is_not_enrolled_twice(): void
    {
        $class = SchoolClass::factory()->create();
        $enrolled = Student::factory()->create();
        $new = Student::factory()->create();
        Enrollment::factory()->for($class)->for($enrolled)->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => [$enrolled->id, $new->id]])
            ->assertSessionHas('success');

        $this->assertSame(1, Enrollment::where('student_id', $enrolled->id)->count());
        $this->assertSame(2, $class->enrollments()->active()->count());
    }

    public function test_inactive_students_are_not_enrolled(): void
    {
        $class = SchoolClass::factory()->create();
        $graduated = Student::factory()->withStatus(StudentStatus::Graduated)->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => [$graduated->id]])
            ->assertSessionHas('error');

        $this->assertSame(0, Enrollment::count());
    }

    public function test_archived_classes_take_no_new_students(): void
    {
        $class = SchoolClass::factory()->archived()->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => [Student::factory()->create()->id]])
            ->assertSessionHasErrors('student_ids');
    }

    public function test_enrollment_input_is_validated(): void
    {
        $class = SchoolClass::factory()->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => []])
            ->assertSessionHasErrors('student_ids');

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/enrollments", ['student_ids' => [999999]])
            ->assertSessionHasErrors('student_ids.0');
    }

    public function test_withdrawing_keeps_the_enrollment_as_history(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/enrollments/{$enrollment->id}/withdraw")
            ->assertSessionHas('success');

        $enrollment->refresh();

        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->status);
        $this->assertNotNull($enrollment->ended_at);
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_withdrawn_student_can_be_enrolled_again(): void
    {
        $enrollment = Enrollment::factory()->withdrawn()->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$enrollment->school_class_id}/enrollments", ['student_ids' => [$enrollment->student_id]])
            ->assertSessionHas('success');

        $this->assertSame(2, Enrollment::where('student_id', $enrollment->student_id)->count());
        $this->assertSame(1, Enrollment::where('student_id', $enrollment->student_id)->active()->count());
    }

    public function test_the_class_page_shows_the_roster_and_history(): void
    {
        $class = SchoolClass::factory()->create();
        Enrollment::factory()->for($class)->count(2)->create();
        Enrollment::factory()->for($class)->withdrawn()->create();

        $this->actingAs($this->admin())
            ->get("/classes/{$class->id}")
            ->assertInertia(fn ($page) => $page
                ->has('roster.current', 2)
                ->has('roster.past', 1)
                ->missing('candidates')
            );
    }

    public function test_candidates_are_loaded_on_request_and_exclude_current_students(): void
    {
        $class = SchoolClass::factory()->create();
        $inClass = Student::factory()->create(['last_name' => 'Inclass']);
        Enrollment::factory()->for($class)->for($inClass)->create();
        Student::factory()->create(['last_name' => 'Available']);
        Student::factory()->withStatus(StudentStatus::Dropped)->create(['last_name' => 'Dropped']);

        $this->actingAs($this->admin())
            ->get("/classes/{$class->id}")
            ->assertInertia(fn ($page) => $page
                ->missing('candidates')
                ->reloadOnly('candidates', fn ($reload) => $reload
                    ->has('candidates', 1)
                    ->where('candidates.0.name', fn ($name) => str_starts_with($name, 'Available'))
                )
            );
    }

    public function test_a_students_enrollment_history_is_shown_on_their_profile(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->withdrawn()->create();
        Enrollment::factory()->for($student)->create();

        $this->actingAs($this->admin())
            ->get("/students/{$student->id}")
            ->assertInertia(fn ($page) => $page->has('enrollments', 2));

        $this->actingAs($student->user)
            ->get("/students/{$student->id}")
            ->assertInertia(fn ($page) => $page->has('enrollments', 2));
    }

    public function test_students_with_class_history_are_kept(): void
    {
        $enrollment = Enrollment::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/students/{$enrollment->student_id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/classes/{$enrollment->school_class_id}")->assertSessionHas('error');

        $this->assertNotNull($enrollment->student->fresh());
        $this->assertNotNull($enrollment->schoolClass->fresh());
    }

    public function test_teachers_and_students_cannot_enroll_or_withdraw(): void
    {
        $class = SchoolClass::factory()->create();
        $enrollment = Enrollment::factory()->for($class)->create();
        $student = Student::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student, RoleName::Hr] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)
                ->post("/classes/{$class->id}/enrollments", ['student_ids' => [$student->id]])
                ->assertForbidden();
            $this->actingAs($user)->patch("/enrollments/{$enrollment->id}/withdraw")->assertForbidden();
        }

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        $this->assertSame(1, Enrollment::count());
    }
}
