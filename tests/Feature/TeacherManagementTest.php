<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function teacherData(array $overrides = []): array
    {
        return [
            'employee_number' => 'T-0107',
            'first_name' => 'Rosa',
            'middle_name' => null,
            'last_name' => 'Santos',
            'birth_date' => '1985-03-02',
            'gender' => 'female',
            'contact_number' => '0918 765 4321',
            'specialization' => 'Mathematics',
            'status' => 'active',
            'email' => 'rosa.santos@school.test',
            'password' => 'teacher-password',
            'password_confirmation' => 'teacher-password',
            ...$overrides,
        ];
    }

    public function test_hr_can_add_a_teacher_with_a_linked_account(): void
    {
        $hr = User::factory()->withRole(RoleName::Hr)->create();

        $this->actingAs($hr)->post('/teachers', $this->teacherData())->assertSessionHasNoErrors();

        $teacher = Teacher::with('user')->firstWhere('employee_number', 'T-0107');

        $this->assertSame('Rosa Santos', $teacher->full_name);
        $this->assertSame('Mathematics', $teacher->specialization);
        $this->assertTrue($teacher->user->hasRole('teacher'));
        $this->assertSame('rosa.santos@school.test', $teacher->user->email);
    }

    public function test_admin_can_manage_teachers(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)->get('/teachers')->assertOk();
        $this->actingAs($admin)
            ->put("/teachers/{$teacher->id}", $this->teacherData([
                'employee_number' => $teacher->employee_number,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Rosa Santos', $teacher->fresh()->full_name);
        $this->assertSame('Rosa Santos', $teacher->user->fresh()->name);
    }

    public function test_employee_number_must_be_unique(): void
    {
        Teacher::factory()->create(['employee_number' => 'T-0107']);

        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())
            ->post('/teachers', $this->teacherData())
            ->assertSessionHasErrors('employee_number');
    }

    public function test_teachers_can_be_searched_and_filtered(): void
    {
        $hr = User::factory()->withRole(RoleName::Hr)->create();
        Teacher::factory()->create(['last_name' => 'Santos', 'specialization' => 'Mathematics']);
        Teacher::factory()->create(['last_name' => 'Reyes', 'specialization' => 'Science']);
        Teacher::factory()->withStatus(TeacherStatus::OnLeave)->create(['specialization' => 'History']);

        $this->actingAs($hr)->get('/teachers?search=science')
            ->assertInertia(fn ($page) => $page->has('teachers.data', 1));

        $this->actingAs($hr)->get('/teachers?status=on_leave')
            ->assertInertia(fn ($page) => $page->has('teachers.data', 1)->where('teachers.data.0.specialization', 'History'));
    }

    public function test_status_controls_whether_the_teacher_can_sign_in(): void
    {
        $teacher = Teacher::factory()->create();
        $hr = User::factory()->withRole(RoleName::Hr)->create();

        $this->actingAs($hr)->patch("/teachers/{$teacher->id}/status", ['status' => 'on_leave']);
        $this->assertTrue($teacher->user->fresh()->is_active);

        $this->actingAs($hr)->patch("/teachers/{$teacher->id}/status", ['status' => 'resigned']);
        $this->assertSame(TeacherStatus::Resigned, $teacher->fresh()->status);
        $this->assertFalse($teacher->user->fresh()->is_active);
    }

    public function test_a_teacher_can_view_their_own_profile_but_not_manage_teachers(): void
    {
        $teacher = Teacher::factory()->create();
        $colleague = Teacher::factory()->create(['first_name' => 'Unchanged']);

        $this->actingAs($teacher->user)
            ->get("/teachers/{$teacher->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false));

        $this->actingAs($teacher->user)->get('/teachers')->assertForbidden();
        $this->actingAs($teacher->user)->get("/teachers/{$colleague->id}")->assertForbidden();
        $this->actingAs($teacher->user)->post('/teachers', $this->teacherData())->assertForbidden();
        $this->actingAs($teacher->user)
            ->put("/teachers/{$colleague->id}", $this->teacherData())
            ->assertForbidden();
        $this->actingAs($teacher->user)
            ->patch("/teachers/{$teacher->id}/status", ['status' => 'resigned'])
            ->assertForbidden();

        $this->assertSame('Unchanged', $colleague->fresh()->first_name);
    }

    public function test_students_cannot_see_teacher_records(): void
    {
        $teacher = Teacher::factory()->create();
        $student = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($student)->get('/teachers')->assertForbidden();
        $this->actingAs($student)->get("/teachers/{$teacher->id}")->assertForbidden();
    }

    public function test_deleting_a_teacher_removes_their_account(): void
    {
        $teacher = Teacher::factory()->create();
        $userId = $teacher->user_id;

        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())
            ->delete("/teachers/{$teacher->id}")
            ->assertRedirect('/teachers');

        $this->assertNull(User::find($userId));
    }

    public function test_a_teacher_account_cannot_be_deleted_from_the_users_screen(): void
    {
        $teacher = Teacher::factory()->create();

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->delete("/users/{$teacher->user_id}")
            ->assertForbidden();
    }
}
