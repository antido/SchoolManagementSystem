<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\RoleName;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\GradingScale;
use App\Models\GradingSystem;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\GradingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradingSeeder::class);

        $this->classSubject = ClassSubject::factory()->create();
        $this->student = $this->enroll();
    }

    private function enroll(): Student
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $this->classSubject->school_class_id]);

        return $student;
    }

    private function activity(ActivityType $type, float $points, array $attributes = []): LearningActivity
    {
        return LearningActivity::factory()->for($this->classSubject)->ofType($type)->published()->create(['total_points' => $points, ...$attributes]);
    }

    private function gradedSubmission(LearningActivity $activity, float $score, ?Student $student = null): ActivitySubmission
    {
        return ActivitySubmission::factory()->for($activity)->for($student ?? $this->student)->graded($score)->create();
    }

    private function gradedAttempt(LearningActivity $activity, float $score, int $number = 1): ActivityAttempt
    {
        return ActivityAttempt::factory()->for($activity)->for($this->student)->create(['score' => $score, 'attempt_number' => $number]);
    }

    private function compute(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->classSubject->teacher->user)->post(route('grades.compute', $this->classSubject));
    }

    private function currentGrade(?Student $student = null): StudentGrade
    {
        return StudentGrade::query()->where('student_id', ($student ?? $this->student)->id)->current()->sole();
    }

    public function test_grades_combine_assignment_quiz_and_exam_scores_by_weight(): void
    {
        $this->gradedSubmission($this->activity(ActivityType::Assignment, 20), 18);
        $this->gradedAttempt($this->activity(ActivityType::Quiz, 10), 8);
        $this->gradedAttempt($this->activity(ActivityType::Examination, 40), 30);

        $this->compute()->assertSessionHas('success', '1 grade was updated.');

        $grade = $this->currentGrade();
        // (90*20 + 80*20 + 75*40) / 80; no projects yet, so their weight is left out.
        $this->assertSame('80.00', $grade->percentage);
        $this->assertSame('Satisfactory', $grade->descriptor);
        $this->assertTrue($grade->passed);
        $this->assertSame(['Assignments', 'Quizzes', 'Examinations', 'Projects and other work'], array_column($grade->breakdown, 'name'));
        $this->assertSame($this->classSubject->teacher->user_id, $grade->computed_by);
    }

    public function test_missing_work_counts_as_zero_once_past_due(): void
    {
        $this->gradedSubmission($this->activity(ActivityType::Assignment, 10), 10);
        $this->activity(ActivityType::Assignment, 10, ['due_at' => now()->subDay()]);

        $this->compute();

        $this->assertSame('50.00', $this->currentGrade()->percentage);
        $this->assertFalse($this->currentGrade()->passed);
    }

    public function test_work_not_due_yet_or_not_graded_yet_is_left_out(): void
    {
        $this->gradedSubmission($this->activity(ActivityType::Assignment, 10), 9);
        $this->activity(ActivityType::Assignment, 10, ['due_at' => now()->addWeek()]);
        ActivitySubmission::factory()->for($this->activity(ActivityType::Assignment, 10))->for($this->student)->create();
        $this->activity(ActivityType::Assignment, 50)->forceFill(['status' => 'draft'])->save();

        $this->compute();

        $this->assertSame('90.00', $this->currentGrade()->percentage);
    }

    public function test_the_best_graded_attempt_counts(): void
    {
        $quiz = $this->activity(ActivityType::Quiz, 10, ['max_attempts' => 3]);
        $this->gradedAttempt($quiz, 4, 1);
        $this->gradedAttempt($quiz, 9, 2);
        ActivityAttempt::factory()->for($quiz)->for($this->student)->create(['score' => 10, 'attempt_number' => 3, 'status' => 'submitted']);

        $this->compute();

        $this->assertSame('90.00', $this->currentGrade()->percentage);
    }

    public function test_computing_again_keeps_history_and_skips_unchanged_grades(): void
    {
        $submission = $this->gradedSubmission($this->activity(ActivityType::Assignment, 20), 10);

        $this->compute();
        $this->compute()->assertSessionHas('success', 'Grades are up to date.');
        $this->assertSame(1, StudentGrade::count());

        $submission->forceFill(['score' => 20])->save();
        $this->compute();

        $this->assertSame(2, StudentGrade::count());
        $this->assertSame('100.00', $this->currentGrade()->percentage);
        $this->assertNotNull(StudentGrade::query()->where('percentage', 50)->sole()->superseded_at);

        $this->actingAs($this->student->user)
            ->get(route('grades.student', [$this->classSubject, $this->student]))
            ->assertInertia(fn ($page) => $page
                ->component('Grades/Student')
                ->has('history', 2)
                ->where('history.0.percentage', 100)
                ->where('history.1.percentage', 50)
            );
    }

    public function test_the_scale_decides_descriptor_and_pass_or_fail_at_the_boundaries(): void
    {
        $scale = GradingScale::default()->load('bands');

        $this->assertSame('Fairly satisfactory', $scale->describe(75));
        $this->assertTrue($scale->passes(75));
        $this->assertSame('Did not meet expectations', $scale->describe(74.99));
        $this->assertFalse($scale->passes(74.99));
        $this->assertSame('Outstanding', $scale->describe(100));
    }

    public function test_a_class_subject_can_use_its_own_grading_system(): void
    {
        $system = GradingSystem::create(['name' => 'Exams only', 'grading_scale_id' => GradingScale::default()->id]);
        $system->components()->create(['name' => 'Exams', 'weight' => 100, 'activity_types' => ['examination'], 'order' => 1]);

        $this->gradedSubmission($this->activity(ActivityType::Assignment, 10), 0);
        $this->gradedAttempt($this->activity(ActivityType::Examination, 50), 45);

        $this->actingAs($this->classSubject->teacher->user)
            ->patch(route('grades.system', $this->classSubject), ['grading_system_id' => $system->id])
            ->assertSessionHas('success');
        $this->compute();

        $this->assertSame('90.00', $this->currentGrade()->percentage);
    }

    public function test_only_people_who_manage_the_subject_compute_or_see_everyones_grades(): void
    {
        $this->gradedSubmission($this->activity(ActivityType::Assignment, 10), 8);

        $this->compute(Teacher::factory()->create()->user)->assertForbidden();
        $this->compute($this->student->user)->assertForbidden();
        $this->compute(User::factory()->withRole(RoleName::Admin)->create())->assertSessionHas('success');

        $this->actingAs($this->classSubject->teacher->user)
            ->get(route('class-subjects.show', $this->classSubject))
            ->assertInertia(fn ($page) => $page->where('grades.rows.0.grade.percentage', 80)->where('grades.can_manage', true));

        // Students see the class subject but not the class's grades.
        $this->actingAs($this->student->user)
            ->get(route('class-subjects.show', $this->classSubject))
            ->assertInertia(fn ($page) => $page->missing('grades'));
    }

    public function test_students_see_only_their_own_grades(): void
    {
        $classmate = $this->enroll();
        $assignment = $this->activity(ActivityType::Assignment, 10);
        $this->gradedSubmission($assignment, 8);
        $this->gradedSubmission($assignment, 6, $classmate);
        $this->compute();

        $this->actingAs($this->student->user)
            ->get(route('grades.index'))
            ->assertInertia(fn ($page) => $page->component('Grades/Mine')->has('grades', 1)->where('grades.0.percentage', 80));

        $this->actingAs($this->student->user)
            ->get(route('grades.student', [$this->classSubject, $classmate]))
            ->assertForbidden();
    }

    public function test_teachers_list_the_subjects_whose_grades_they_manage(): void
    {
        ClassSubject::factory()->create();
        $this->gradedSubmission($this->activity(ActivityType::Assignment, 10), 7);
        $this->compute();

        $this->actingAs($this->classSubject->teacher->user)
            ->get(route('grades.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Grades/Index')
                ->has('classSubjects.data', 1)
                ->where('classSubjects.data.0.graded_count', 1)
                ->where('classSubjects.data.0.average', 70)
            );
    }

    public function test_computing_without_a_grading_system_explains_what_to_do(): void
    {
        GradingSystem::query()->update(['is_default' => false]);

        $this->compute()->assertSessionHas('error');
    }

    public function test_admins_configure_grading_systems_whose_weights_add_up_to_100(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();
        $scale = GradingScale::default();

        $payload = fn (array $components) => [
            'name' => 'Seniors',
            'grading_scale_id' => $scale->id,
            'components' => $components,
        ];

        $this->actingAs($admin)->post(route('grading.systems.store'), $payload([
            ['name' => 'Work', 'weight' => 60, 'activity_types' => ['assignment']],
            ['name' => 'Exams', 'weight' => 30, 'activity_types' => ['examination']],
        ]))->assertSessionHasErrors('components');

        $this->actingAs($admin)->post(route('grading.systems.store'), $payload([
            ['name' => 'Work', 'weight' => 50, 'activity_types' => ['assignment', 'quiz']],
            ['name' => 'Exams', 'weight' => 50, 'activity_types' => ['quiz']],
        ]))->assertSessionHasErrors('components');

        $this->actingAs($admin)->post(route('grading.systems.store'), [...$payload([
            ['name' => 'Work', 'weight' => 40, 'activity_types' => ['assignment', 'quiz']],
            ['name' => 'Exams', 'weight' => 60, 'activity_types' => ['examination']],
        ]), 'is_default' => true])->assertRedirect(route('grading.index'));

        $system = GradingSystem::where('name', 'Seniors')->sole();
        $this->assertTrue($system->is_default);
        $this->assertSame(1, GradingSystem::where('is_default', true)->count());
        $this->assertCount(2, $system->components);
    }

    public function test_grading_scales_need_a_band_starting_at_zero(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();

        $this->actingAs($admin)->post(route('grading.scales.store'), [
            'name' => 'Letters',
            'passing_percentage' => 60,
            'bands' => [['min_percentage' => 90, 'label' => 'A'], ['min_percentage' => 60, 'label' => 'D']],
        ])->assertSessionHasErrors('bands');

        $this->actingAs($admin)->post(route('grading.scales.store'), [
            'name' => 'Letters',
            'passing_percentage' => 60,
            'bands' => [['min_percentage' => 90, 'label' => 'A'], ['min_percentage' => 60, 'label' => 'D'], ['min_percentage' => 0, 'label' => 'F']],
        ])->assertRedirect(route('grading.index'));

        $this->assertSame('D', GradingScale::where('name', 'Letters')->sole()->load('bands')->describe(65));
    }

    public function test_teachers_cannot_change_school_grading_settings(): void
    {
        $teacher = $this->classSubject->teacher->user;

        $this->actingAs($teacher)->get(route('grading.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('grading.scales.store'), [])->assertForbidden();
    }

    public function test_grading_systems_in_use_are_kept(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();
        $default = GradingSystem::default();

        $this->actingAs($admin)->delete(route('grading.systems.destroy', $default))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('grading.index'))
            ->assertInertia(fn ($page) => $page->where('systems.0.can_delete', false)->where('scales.0.can_delete', false));
    }
}
