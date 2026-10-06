<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function studentData(array $overrides = []): array
    {
        return [
            'student_number' => 'S-2026-0001',
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'last_name' => 'Cruz',
            'birth_date' => '2012-05-14',
            'gender' => 'female',
            'address' => '12 Mabini St, Baguio City',
            'contact_number' => '0917 123 4567',
            'status' => 'active',
            'email' => 'ana.cruz@school.test',
            'password' => 'student-password',
            'password_confirmation' => 'student-password',
            ...$overrides,
        ];
    }

    public function test_admin_can_create_a_student_with_a_linked_account(): void
    {
        $this->actingAs($this->admin())
            ->post('/students', $this->studentData())
            ->assertSessionHasNoErrors();

        $student = Student::with('user')->firstWhere('student_number', 'S-2026-0001');

        $this->assertSame('Ana Maria Cruz', $student->full_name);
        $this->assertSame(StudentStatus::Active, $student->status);
        $this->assertSame('ana.cruz@school.test', $student->user->email);
        $this->assertSame('Ana Maria Cruz', $student->user->name);
        $this->assertTrue($student->user->hasRole('student'));
        $this->assertTrue($student->user->is_active);
    }

    public function test_student_number_must_be_unique(): void
    {
        Student::factory()->create(['student_number' => 'S-2026-0001']);

        $this->actingAs($this->admin())
            ->post('/students', $this->studentData())
            ->assertSessionHasErrors('student_number');
    }

    public function test_student_fields_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post('/students', $this->studentData([
                'student_number' => 'bad number!',
                'first_name' => '',
                'last_name' => '',
                'birth_date' => '2099-01-01',
                'gender' => 'unicorn',
                'contact_number' => 'call me',
                'status' => 'expelled',
                'email' => 'not-an-email',
                'password' => 'short',
            ]))
            ->assertSessionHasErrors([
                'student_number', 'first_name', 'last_name', 'birth_date',
                'gender', 'contact_number', 'status', 'email', 'password',
            ]);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_nothing_is_saved_if_the_account_cannot_be_created(): void
    {
        User::factory()->create(['email' => 'ana.cruz@school.test']);

        $this->actingAs($this->admin())
            ->post('/students', $this->studentData())
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_students_can_be_searched_and_filtered(): void
    {
        $admin = $this->admin();
        Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'student_number' => 'S-1']);
        Student::factory()->create(['first_name' => 'Ben', 'last_name' => 'Diaz', 'student_number' => 'S-2']);
        // Fixed values, so a random student number like S-2xxxxx cannot match the searches.
        Student::factory()->withStatus(StudentStatus::Graduated)->create(['first_name' => 'Cara', 'last_name' => 'Evans', 'student_number' => 'S-3']);

        $this->actingAs($admin)->get('/students?search=cruz')
            ->assertInertia(fn ($page) => $page->has('students.data', 1)->where('students.data.0.full_name', 'Ana Cruz'));

        $this->actingAs($admin)->get('/students?search=S-2')
            ->assertInertia(fn ($page) => $page->has('students.data', 1)->where('students.data.0.full_name', 'Ben Diaz'));

        $this->actingAs($admin)->get('/students?status=graduated')
            ->assertInertia(fn ($page) => $page->has('students.data', 1));
    }

    public function test_admin_can_update_a_student_and_their_account(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin())
            ->put("/students/{$student->id}", $this->studentData([
                'student_number' => $student->student_number,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors();

        $student->refresh()->load('user');

        $this->assertSame('Ana Maria Cruz', $student->full_name);
        $this->assertSame('ana.cruz@school.test', $student->user->email);
        $this->assertSame('Ana Maria Cruz', $student->user->name);
    }

    public function test_status_change_also_controls_the_account(): void
    {
        $student = Student::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/students/{$student->id}/status", ['status' => 'graduated']);

        $this->assertSame(StudentStatus::Graduated, $student->fresh()->status);
        $this->assertFalse($student->user->fresh()->is_active);

        $this->actingAs($admin)->patch("/students/{$student->id}/status", ['status' => 'active']);

        $this->assertTrue($student->user->fresh()->is_active);

        $this->actingAs($admin)
            ->patch("/students/{$student->id}/status", ['status' => 'expelled'])
            ->assertSessionHasErrors('status');
    }

    public function test_user_id_and_status_cannot_be_set_through_the_profile_form(): void
    {
        $student = Student::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($this->admin())->put("/students/{$student->id}", $this->studentData([
            'student_number' => $student->student_number,
            'password' => '',
            'password_confirmation' => '',
            'user_id' => $other->id,
            'status' => 'graduated',
        ]));

        $student->refresh();

        $this->assertNotSame($other->id, $student->user_id);
        $this->assertSame(StudentStatus::Active, $student->status);
    }

    public function test_admin_can_delete_a_student_and_their_account(): void
    {
        $student = Student::factory()->create();
        $userId = $student->user_id;

        $this->actingAs($this->admin())
            ->delete("/students/{$student->id}")
            ->assertRedirect('/students');

        $this->assertNull($student->fresh());
        $this->assertNull(User::find($userId));
    }

    public function test_a_student_account_cannot_be_deleted_from_the_users_screen(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/users/{$student->user_id}")
            ->assertForbidden();

        $this->assertNotNull($student->fresh());
    }

    public function test_a_student_can_view_their_own_profile_but_not_others(): void
    {
        $student = Student::factory()->create();
        $classmate = Student::factory()->create();

        $this->actingAs($student->user)
            ->get("/students/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false)->where('can.delete', false));

        $this->actingAs($student->user)->get("/students/{$classmate->id}")->assertForbidden();
        $this->actingAs($student->user)->get('/students')->assertForbidden();
    }

    public function test_a_student_cannot_use_administrative_functions(): void
    {
        $student = Student::factory()->create();
        $classmate = Student::factory()->create(['first_name' => 'Unchanged']);

        $this->actingAs($student->user)->get('/students/create')->assertForbidden();
        $this->actingAs($student->user)->post('/students', $this->studentData())->assertForbidden();
        $this->actingAs($student->user)->get("/students/{$student->id}/edit")->assertForbidden();
        $this->actingAs($student->user)
            ->put("/students/{$classmate->id}", $this->studentData())
            ->assertForbidden();
        $this->actingAs($student->user)
            ->patch("/students/{$student->id}/status", ['status' => 'graduated'])
            ->assertForbidden();
        $this->actingAs($student->user)->delete("/students/{$classmate->id}")->assertForbidden();

        $this->assertSame('Unchanged', $classmate->fresh()->first_name);
        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
    }

    public function test_hr_cannot_manage_students(): void
    {
        $hr = User::factory()->withRole(RoleName::Hr)->create();

        $this->actingAs($hr)->get('/students')->assertForbidden();
        $this->actingAs($hr)->post('/students', $this->studentData())->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/students')->assertRedirect('/login');
    }
}
