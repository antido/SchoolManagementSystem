<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\AcademicYear;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\GradingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    /** @var list<Student> */
    private array $students;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradingSeeder::class);

        $year = AcademicYear::factory()->active()->create();
        $class = SchoolClass::factory()->for($year)->create();
        $this->classSubject = ClassSubject::factory()->create([
            'school_class_id' => $class->id,
            'subject_id' => Subject::factory()->create(['name' => 'Science'])->id,
        ]);

        $this->students = collect(range(1, 3))->map(function () use ($class) {
            $student = Student::factory()->create();
            Enrollment::factory()->for($student)->for($class)->create();

            return $student;
        })->all();
    }

    private function grade(Student $student, float $percentage): void
    {
        $grade = new StudentGrade;
        $grade->class_subject_id = $this->classSubject->id;
        $grade->student_id = $student->id;
        $grade->percentage = $percentage;
        $grade->descriptor = '';
        $grade->passed = $percentage >= 75;
        $grade->breakdown = [];
        $grade->computed_at = now();
        $grade->save();
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    public function test_the_figures_add_up(): void
    {
        [$a, $b, $c] = $this->students;
        $this->grade($a, 95);
        $this->grade($b, 82);
        $this->grade($c, 60);

        // Attendance: 3 attended of 4 counted days; the excused day is left out.
        foreach (['present', 'late', 'present', 'absent', 'excused'] as $daysAgo => $status) {
            Attendance::factory()->for($a)->status(AttendanceStatus::from($status))->create([
                'school_class_id' => $this->classSubject->school_class_id,
                'attendance_date' => today()->subDays($daysAgo),
            ]);
        }

        // One past-due assignment: 2 of 3 students handed in.
        $assignment = LearningActivity::factory()->for($this->classSubject)->published()->create(['due_at' => now()->subDay()]);
        ActivitySubmission::factory()->for($assignment)->for($a)->create();
        ActivitySubmission::factory()->for($assignment)->for($b)->create();

        $quiz = LearningActivity::factory()->for($this->classSubject)->ofType(ActivityType::Quiz)->published()->create(['total_points' => 20]);
        ActivityAttempt::factory()->for($quiz)->for($a)->create(['score' => 18]);
        ActivityAttempt::factory()->for($quiz)->for($b)->create(['score' => 14]);

        $this->actingAs($this->admin())
            ->get(route('analytics'))
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/Index')
                ->where('stats.students', 3)
                ->where('stats.average_grade', 79)
                ->where('stats.attendance_rate', 75)
                ->where('stats.completion_rate', 66.67)
                ->where('stats.exam_average', 80)
                ->where('gradeDistribution', fn ($bands) => collect($bands)->pluck('value', 'label')->all() === [
                    'Did not meet expectations' => 1,
                    'Fairly satisfactory' => 0,
                    'Satisfactory' => 1,
                    'Very satisfactory' => 0,
                    'Outstanding' => 1,
                ])
                ->where('examBySubject', [['label' => 'Science', 'value' => 80]])
                ->where('topStudents.0.id', $a->id)
                ->has('attendanceTrend', 12)
            );
    }

    public function test_students_needing_attention_are_listed_with_reasons(): void
    {
        [$good, $failing, $absent] = $this->students;
        $this->grade($good, 90);
        $this->grade($failing, 60);
        $this->grade($absent, 85);

        foreach (range(0, 4) as $daysAgo) {
            Attendance::factory()->for($absent)->status($daysAgo < 2 ? AttendanceStatus::Present : AttendanceStatus::Absent)->create([
                'school_class_id' => $this->classSubject->school_class_id,
                'attendance_date' => today()->subDays($daysAgo),
            ]);
        }

        LearningActivity::factory()->for($this->classSubject)->published()->count(3)->create(['due_at' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->get(route('analytics'))
            ->assertInertia(fn ($page) => $page->where('needsAttention', function ($rows) use ($failing, $absent, $good) {
                $byId = collect($rows)->keyBy('id');

                return $byId[$failing->id]['reasons'] === ['Failing average', 'Missing work']
                    && $byId[$absent->id]['reasons'] === ['Low attendance', 'Missing work']
                    && $byId[$absent->id]['attendance'] == 40
                    && $byId[$good->id]['reasons'] === ['Missing work']
                    && $byId[$good->id]['missing'] === 3;
            }));
    }

    public function test_teachers_see_only_their_own_classes(): void
    {
        $this->grade($this->students[0], 70);

        $otherClass = SchoolClass::factory()->for($this->classSubject->schoolClass->academicYear)->create();
        $outsider = Student::factory()->create();
        Enrollment::factory()->for($outsider)->for($otherClass)->create();

        $this->actingAs($this->classSubject->teacher->user)
            ->get(route('analytics'))
            ->assertInertia(fn ($page) => $page->where('stats.students', 3)->where('stats.classes', 1)->has('options.classes', 1));

        // A class they do not teach, given in the address, is ignored.
        $this->actingAs($this->classSubject->teacher->user)
            ->get(route('analytics', ['school_class_id' => $otherClass->id]))
            ->assertInertia(fn ($page) => $page->where('filters.school_class_id', null)->where('stats.students', 3));
    }

    public function test_figures_can_be_narrowed_to_a_class(): void
    {
        $other = SchoolClass::factory()->for($this->classSubject->schoolClass->academicYear)->create();
        Enrollment::factory()->for(Student::factory()->create())->for($other)->create();

        $this->actingAs($this->admin())
            ->get(route('analytics', ['school_class_id' => $other->id]))
            ->assertInertia(fn ($page) => $page->where('stats.students', 1)->where('stats.classes', 1));
    }

    public function test_students_and_hr_cannot_open_analytics(): void
    {
        $this->actingAs($this->students[0]->user)->get(route('analytics'))->assertForbidden();
        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())->get(route('analytics'))->assertForbidden();
    }

    public function test_the_page_runs_a_bounded_number_of_queries(): void
    {
        // More students should not mean more queries.
        foreach (range(1, 20) as $ignored) {
            $student = Student::factory()->create();
            Enrollment::factory()->for($student)->create(['school_class_id' => $this->classSubject->school_class_id]);
            $this->grade($student, 80);
        }

        $admin = $this->admin();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('analytics'))->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertLessThan(45, $queries, "The analytics page ran {$queries} queries.");
    }

    public function test_cached_figures_come_back_intact(): void
    {
        // The array store used by the tests keeps values as they are; the
        // database store serializes them, as in development and production.
        config(['cache.default' => 'database']);
        $this->grade($this->students[0], 90);
        $admin = $this->admin();

        // The first visit computes the figures, the second reads them from the cache.
        foreach ([1, 2] as $visit) {
            $this->actingAs($admin)
                ->get(route('analytics'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('enrollmentByClass', 1)
                    ->where('enrollmentByClass.0.value', 3)
                    ->has('topStudents', 1)
                    ->has('gradeDistribution'));
        }
    }
}
