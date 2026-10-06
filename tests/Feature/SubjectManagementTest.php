<?php

namespace Tests\Feature;

use App\Enums\ActiveStatus;
use App\Enums\RoleName;
use App\Enums\TeacherStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    /**
     * A class with one term in its academic year.
     *
     * @return array{0: SchoolClass, 1: AcademicTerm}
     */
    private function classWithTerm(): array
    {
        $year = AcademicYear::factory()->create();
        $class = SchoolClass::factory()->for($year)->create();
        $term = AcademicTerm::factory()->for($year)->create();

        return [$class, $term];
    }

    public function test_admin_can_create_and_edit_a_subject(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/subjects', ['code' => 'MATH7', 'name' => 'Mathematics 7', 'description' => null, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $subject = Subject::firstWhere('code', 'MATH7');

        $this->actingAs($admin)
            ->put("/subjects/{$subject->id}", ['code' => 'MATH7', 'name' => 'Mathematics Seven', 'status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Mathematics Seven', $subject->fresh()->name);
        $this->assertSame(ActiveStatus::Inactive, $subject->fresh()->status);
    }

    public function test_subject_codes_are_unique(): void
    {
        Subject::factory()->create(['code' => 'MATH7']);

        $this->actingAs($this->admin())
            ->post('/subjects', ['code' => 'MATH7', 'name' => 'Duplicate', 'status' => 'active'])
            ->assertSessionHasErrors('code');
    }

    public function test_subjects_can_be_searched(): void
    {
        Subject::factory()->create(['code' => 'SCI7', 'name' => 'Science 7']);
        Subject::factory()->create(['code' => 'ENG7', 'name' => 'English 7']);

        $this->actingAs($this->admin())->get('/subjects?search=sci')
            ->assertInertia(fn ($page) => $page->has('subjects.data', 1)->where('subjects.data.0.name', 'Science 7'));
    }

    public function test_a_subject_can_be_assigned_to_a_class_with_a_teacher(): void
    {
        [$class, $term] = $this->classWithTerm();
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/subjects", [
                'subject_id' => $subject->id,
                'academic_term_id' => $term->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $classSubject = ClassSubject::first();

        $this->assertSame($class->id, $classSubject->school_class_id);
        $this->assertSame($teacher->id, $classSubject->teacher_id);
        $this->assertSame($term->id, $classSubject->academic_term_id);
    }

    public function test_duplicate_class_subject_in_the_same_term_is_refused(): void
    {
        [$class, $term] = $this->classWithTerm();
        $subject = Subject::factory()->create();
        ClassSubject::factory()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'academic_term_id' => $term->id]);

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/subjects", ['subject_id' => $subject->id, 'academic_term_id' => $term->id])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, ClassSubject::count());
    }

    public function test_the_same_subject_can_be_taken_in_another_term(): void
    {
        [$class, $first] = $this->classWithTerm();
        $second = AcademicTerm::factory()->for($class->academicYear)->create();
        $subject = Subject::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/classes/{$class->id}/subjects", ['subject_id' => $subject->id, 'academic_term_id' => $first->id]);
        $this->actingAs($admin)
            ->post("/classes/{$class->id}/subjects", ['subject_id' => $subject->id, 'academic_term_id' => $second->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassSubject::count());
    }

    public function test_the_term_must_belong_to_the_classs_year(): void
    {
        [$class] = $this->classWithTerm();
        $otherYearTerm = AcademicTerm::factory()->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/subjects", ['subject_id' => Subject::factory()->create()->id, 'academic_term_id' => $otherYearTerm->id])
            ->assertSessionHasErrors('academic_term_id');
    }

    public function test_inactive_subjects_and_teachers_cannot_be_assigned(): void
    {
        [$class, $term] = $this->classWithTerm();
        $inactiveSubject = Subject::factory()->create(['status' => ActiveStatus::Inactive]);
        $resigned = Teacher::factory()->withStatus(TeacherStatus::Resigned)->create();

        $this->actingAs($this->admin())
            ->post("/classes/{$class->id}/subjects", [
                'subject_id' => $inactiveSubject->id,
                'academic_term_id' => $term->id,
                'teacher_id' => $resigned->id,
            ])
            ->assertSessionHasErrors(['subject_id', 'teacher_id']);
    }

    public function test_the_teacher_of_a_class_subject_can_be_changed(): void
    {
        $classSubject = ClassSubject::factory()->withoutTeacher()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->put("/class-subjects/{$classSubject->id}", ['teacher_id' => $teacher->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame($teacher->id, $classSubject->fresh()->teacher_id);
    }

    public function test_editing_cannot_move_a_class_subject_to_another_class(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $otherClass = SchoolClass::factory()->create();

        $this->actingAs($this->admin())->put("/class-subjects/{$classSubject->id}", [
            'school_class_id' => $otherClass->id,
            'teacher_id' => null,
            'status' => 'active',
        ]);

        $this->assertSame($classSubject->school_class_id, $classSubject->fresh()->school_class_id);
    }

    public function test_class_subject_detail_page_shows_its_context(): void
    {
        $classSubject = ClassSubject::factory()->create();

        $this->actingAs($this->admin())
            ->get("/class-subjects/{$classSubject->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClassSubjects/Show')
                ->where('classSubject.subject.id', $classSubject->subject_id)
                ->where('classSubject.school_class.id', $classSubject->school_class_id)
                ->where('classSubject.teacher.id', $classSubject->teacher_id)
            );
    }

    public function test_class_page_lists_its_subjects(): void
    {
        $classSubject = ClassSubject::factory()->create();

        $this->actingAs($this->admin())
            ->get("/classes/{$classSubject->school_class_id}")
            ->assertInertia(fn ($page) => $page->has('classSubjects', 1)->has('options.subjects'));
    }

    public function test_taught_subjects_terms_and_classes_are_kept_for_history(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/subjects/{$classSubject->subject_id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/academic-terms/{$classSubject->academic_term_id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/classes/{$classSubject->school_class_id}")->assertSessionHas('error');

        $this->assertNotNull($classSubject->subject->fresh());
        $this->assertNotNull($classSubject->academicTerm->fresh());
        $this->assertNotNull($classSubject->schoolClass->fresh());
    }

    public function test_teachers_and_students_cannot_change_class_subjects(): void
    {
        [$class, $term] = $this->classWithTerm();
        $classSubject = ClassSubject::factory()->create();
        $subject = Subject::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)->post('/subjects', ['code' => 'X1', 'name' => 'X', 'status' => 'active'])->assertForbidden();
            $this->actingAs($user)
                ->post("/classes/{$class->id}/subjects", ['subject_id' => $subject->id, 'academic_term_id' => $term->id])
                ->assertForbidden();
            $this->actingAs($user)
                ->put("/class-subjects/{$classSubject->id}", ['teacher_id' => null, 'status' => 'inactive'])
                ->assertForbidden();
            $this->actingAs($user)->delete("/class-subjects/{$classSubject->id}")->assertForbidden();
        }

        $this->assertSame(ActiveStatus::Active, $classSubject->fresh()->status);
    }
}
