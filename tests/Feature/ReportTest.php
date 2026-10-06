<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classSubject = ClassSubject::factory()->create();
        $this->student = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        Enrollment::factory()->for($this->student)->create(['school_class_id' => $this->classSubject->school_class_id]);
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    private function grade(float $percentage, bool $passed = true): StudentGrade
    {
        $grade = new StudentGrade;
        $grade->class_subject_id = $this->classSubject->id;
        $grade->student_id = $this->student->id;
        $grade->percentage = $percentage;
        $grade->descriptor = $passed ? 'Very satisfactory' : 'Did not meet expectations';
        $grade->passed = $passed;
        $grade->breakdown = [];
        $grade->computed_at = now();
        $grade->save();

        return $grade;
    }

    public function test_each_person_sees_the_reports_they_may_run(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index'))
            ->assertInertia(fn ($page) => $page->component('Reports/Index')->where('groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['reports'])->count() === 10));

        // Teachers get the class and student reports, but not school staffing figures.
        $this->actingAs($this->classSubject->teacher->user)
            ->get(route('reports.index'))
            ->assertInertia(fn ($page) => $page->where('groups', fn ($groups) => ! collect($groups)->flatMap(fn ($group) => $group['reports'])->pluck('key')->contains('teacher-workload')));

        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())
            ->get(route('reports.index'))
            ->assertInertia(fn ($page) => $page->where('groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['reports'])->pluck('key')->all() === ['teacher-workload']));

        $this->actingAs($this->student->user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->student->user)->get(route('reports.show', 'student-grades'))->assertForbidden();
    }

    public function test_the_student_grade_report_previews_the_current_grades(): void
    {
        $this->grade(70, false)->forceFill(['superseded_at' => now()])->save();
        $this->grade(88);

        $this->actingAs($this->admin())
            ->get(route('reports.show', ['student-grades', 'student_id' => $this->student->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Show')
                ->where('result.total_rows', 1)
                ->where('result.rows.0.3', 88)
                ->where('result.rows.0.5', 'Passed')
                ->where('result.summary.0.value', '88%')
                ->where('options.student.value', $this->student->id)
            );
    }

    public function test_a_report_waits_for_its_required_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('reports.show', 'student-grades'))
            ->assertInertia(fn ($page) => $page->where('result', null)->where('report.required', ['student_id']));

        $this->actingAs($admin)->get(route('reports.export', ['student-grades', 'csv']))->assertStatus(422);
    }

    public function test_teachers_cannot_report_on_students_or_classes_outside_their_own(): void
    {
        $teacher = $this->classSubject->teacher->user;
        $stranger = Student::factory()->create();
        $otherSubject = ClassSubject::factory()->create();

        $this->actingAs($teacher)->get(route('reports.show', ['student-grades', 'student_id' => $this->student->id]))->assertOk();
        $this->actingAs($teacher)->get(route('reports.show', ['student-grades', 'student_id' => $stranger->id]))->assertForbidden();
        $this->actingAs($teacher)->get(route('reports.export', ['student-grades', 'csv', 'student_id' => $stranger->id]))->assertForbidden();
        $this->actingAs($teacher)->get(route('reports.show', ['assignment-results', 'class_subject_id' => $otherSubject->id]))->assertForbidden();
        $this->actingAs($teacher)->get(route('reports.show', ['class-attendance', 'school_class_id' => $otherSubject->school_class_id]))->assertForbidden();
    }

    public function test_reports_export_as_csv_with_formula_text_neutralised(): void
    {
        $assignment = LearningActivity::factory()->for($this->classSubject)->published()->create(['title' => '=HYPERLINK("http://x")', 'total_points' => 20]);
        ActivitySubmission::factory()->for($assignment)->for($this->student)->graded(15)->create();

        $response = $this->actingAs($this->admin())->get(route('reports.export', ['assignment-results', 'csv', 'class_subject_id' => $this->classSubject->id]));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Activity,Student,"Student number",Status,Score', $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://x"")",', $csv);
        $this->assertStringContainsString('"Cruz, Ana"', $csv);
        $this->assertStringContainsString('"15 / 20"', $csv);
    }

    public function test_reports_export_as_readable_excel_files(): void
    {
        $this->grade(91.5);

        $response = $this->actingAs($this->admin())->get(route('reports.export', ['class-grades', 'xlsx', 'school_class_id' => $this->classSubject->school_class_id]));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('Class grade report', $sheet->getCell('A1')->getValue());
        $values = collect($sheet->toArray())->flatten()->filter()->values();
        $this->assertTrue($values->contains('Cruz, Ana'));
        $this->assertTrue($values->contains(91.5));
    }

    public function test_reports_export_as_pdf(): void
    {
        StudentAward::factory()->for($this->student)->create();

        $response = $this->actingAs($this->admin())->get(route('reports.export', ['awards', 'pdf']));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_unknown_reports_and_formats_are_not_found(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('reports.show', 'nope'))->assertNotFound();
        $this->actingAs($admin)->get(route('reports.export', ['awards', 'docx']))->assertNotFound();
    }

    public function test_the_class_attendance_report_counts_each_student(): void
    {
        foreach (['present', 'present', 'late', 'absent'] as $daysAgo => $status) {
            Attendance::factory()->for($this->student)->status(AttendanceStatus::from($status))->create([
                'school_class_id' => $this->classSubject->school_class_id,
                'attendance_date' => today()->subDays($daysAgo),
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('reports.show', ['class-attendance', 'school_class_id' => $this->classSubject->school_class_id]))
            ->assertInertia(fn ($page) => $page
                ->where('result.rows.0', ['Cruz, Ana', $this->student->student_number, 2, 1, 1, 0, 75])
                ->where('result.summary.0.value', '75%')
            );
    }

    public function test_the_examination_results_use_the_best_graded_attempt(): void
    {
        $quiz = LearningActivity::factory()->for($this->classSubject)->ofType(ActivityType::Quiz)->published()->create(['total_points' => 10, 'max_attempts' => 2]);
        ActivityAttempt::factory()->for($quiz)->for($this->student)->create(['score' => 6, 'attempt_number' => 1]);
        ActivityAttempt::factory()->for($quiz)->for($this->student)->create(['score' => 9, 'attempt_number' => 2]);

        $this->actingAs($this->admin())
            ->get(route('reports.show', ['examination-results', 'class_subject_id' => $this->classSubject->id]))
            ->assertInertia(fn ($page) => $page
                ->where('result.rows.0.3', 2)
                ->where('result.rows.0.4', '9 / 10')
                ->where('result.rows.0.5', 90)
            );
    }

    public function test_the_teacher_workload_report_counts_classes_subjects_and_students(): void
    {
        $teacher = $this->classSubject->teacher;
        ClassTeacher::factory()->for($this->classSubject->schoolClass)->for($teacher)->adviser()->create();
        ClassSubject::factory()->create(['school_class_id' => $this->classSubject->school_class_id, 'teacher_id' => $teacher->id]);
        LearningActivity::factory()->for($this->classSubject)->create();

        $this->actingAs($this->admin())
            ->get(route('reports.show', ['teacher-workload', 'academic_year_id' => $this->classSubject->schoolClass->academic_year_id]))
            ->assertInertia(fn ($page) => $page->where('result.rows', fn ($rows) => collect($rows)->firstWhere(1, $teacher->employee_number) === [
                $teacher->sortable_name, $teacher->employee_number, 'Active', 1, 1, 2, 1, 1,
            ]));
    }
}
