<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\TeacherStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_get_the_school_overview(): void
    {
        $year = AcademicYear::factory()->active()->create(['name' => '2026-2027']);
        $class = SchoolClass::factory()->for($year)->create();
        Enrollment::factory()->for($class)->count(4)->create();
        Teacher::factory()->count(2)->create();

        foreach ([RoleName::Admin, RoleName::SuperAdmin] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())
                ->get('/dashboard')
                ->assertInertia(fn ($page) => $page
                    ->component('Dashboard')
                    ->where('type', 'admin')
                    ->where('period.year', '2026-2027')
                    ->where('stats.0.value', 4)
                    ->where('stats.2.value', 1)
                    ->where('academic.0.value', 4)
                    ->where('academic.1.value', 4)
                    ->has('recent')
                );
        }
    }

    public function test_teachers_get_their_classes_and_student_counts(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $teacher = Teacher::factory()->create();
        $advised = SchoolClass::factory()->for($year)->create(['name' => 'Advised']);
        $taught = SchoolClass::factory()->for($year)->create(['name' => 'Taught']);
        SchoolClass::factory()->for($year)->create(['name' => 'Not mine']);
        ClassTeacher::factory()->for($advised)->for($teacher)->adviser()->create();
        ClassSubject::factory()->create(['school_class_id' => $taught->id, 'teacher_id' => $teacher->id]);
        Enrollment::factory()->for($advised)->count(3)->create();

        $this->actingAs($teacher->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('type', 'teacher')
                ->has('classes', 2)
                ->where('classes.0.name', 'Advised')
                ->where('classes.0.students', 3)
                ->where('stats.1.value', 3)
            );
    }

    public function test_students_get_their_classes(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create();
        Enrollment::factory()->for($student)->withdrawn()->create();

        $this->actingAs($student->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('type', 'student')
                ->has('classes', 1)
                ->where('studentId', $student->id)
            );
    }

    public function test_hr_gets_teacher_statistics_and_assignment_gaps(): void
    {
        AcademicYear::factory()->active()->create();
        Teacher::factory()->count(2)->create();
        Teacher::factory()->withStatus(TeacherStatus::OnLeave)->create();
        $assigned = Teacher::factory()->create();
        ClassTeacher::factory()->for($assigned)->create();

        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('type', 'hr')
                ->where('stats.0.value', 3)
                ->where('stats.1.value', 1)
                ->where('assignments.0.value', 1)
                ->has('unassignedTeachers', 3)
            );
    }

    public function test_an_account_without_a_role_gets_a_basic_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('type', 'basic')->missing('stats'));
    }

    public function test_dashboards_do_not_leak_admin_data_to_students(): void
    {
        Student::factory()->count(3)->create();
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->missing('stats')->missing('recent'));
    }
}
