<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;

    private Teacher $adviser;

    /** @var list<Student> */
    private array $students;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::factory()->active()->create([
            'start_date' => today()->subMonths(2),
            'end_date' => today()->addMonths(6),
        ]);
        $this->class = SchoolClass::factory()->for($year)->create();
        $this->adviser = Teacher::factory()->create();
        ClassTeacher::factory()->for($this->class)->for($this->adviser)->adviser()->create();

        $this->students = collect(range(1, 3))->map(function () {
            $student = Student::factory()->create();
            Enrollment::factory()->for($student)->for($this->class)->create();

            return $student;
        })->all();
    }

    /**
     * @param  list<string>  $statuses  one per student, in order
     */
    private function sheet(array $statuses, array $extra = []): array
    {
        return [
            'date' => today()->toDateString(),
            'records' => array_map(fn (Student $student, string $status) => [
                'student_id' => $student->id,
                'status' => $status,
                'remarks' => null,
            ], $this->students, $statuses),
            ...$extra,
        ];
    }

    private function record(User $user, array $payload): TestResponse
    {
        return $this->actingAs($user)->put(route('attendance.store', $this->class), $payload);
    }

    public function test_the_adviser_records_daily_attendance_for_the_class(): void
    {
        $this->record($this->adviser->user, $this->sheet(['present', 'absent', 'late']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(3, Attendance::count());
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $this->students[1]->id)->sole()->status);
        $this->assertSame($this->adviser->id, Attendance::first()->teacher_id);
        $this->assertNull(Attendance::first()->class_subject_id);
    }

    public function test_recording_the_same_day_again_corrects_it_without_duplicates(): void
    {
        $this->record($this->adviser->user, $this->sheet(['present', 'absent', 'late']));
        $this->record($this->adviser->user, $this->sheet(['present', 'excused', 'present']));

        $this->assertSame(3, Attendance::count());
        $this->assertSame(AttendanceStatus::Excused, Attendance::where('student_id', $this->students[1]->id)->sole()->status);
    }

    public function test_subject_attendance_is_kept_apart_from_daily_attendance(): void
    {
        $classSubject = ClassSubject::factory()->create(['school_class_id' => $this->class->id]);

        $this->record($this->adviser->user, $this->sheet(['present', 'present', 'present']));
        $this->record($classSubject->teacher->user, $this->sheet(['absent', 'present', 'present'], ['class_subject_id' => $classSubject->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, Attendance::count());
        $this->assertSame(3, Attendance::where('class_subject_id', $classSubject->id)->count());
    }

    public function test_only_the_classes_teachers_and_admins_record_attendance(): void
    {
        $classSubject = ClassSubject::factory()->create(['school_class_id' => $this->class->id]);
        $sheet = $this->sheet(['present', 'present', 'present']);

        // A teacher of another class.
        $this->record(Teacher::factory()->create()->user, $sheet)->assertForbidden();
        // The subject teacher is not the adviser, so not for daily attendance...
        $this->record($classSubject->teacher->user, $sheet)->assertForbidden();
        // ...and the adviser does not teach the subject.
        $this->record($this->adviser->user, [...$sheet, 'class_subject_id' => $classSubject->id])->assertForbidden();
        // A student.
        $this->record($this->students[0]->user, $sheet)->assertForbidden();
        // An ended assignment no longer counts.
        $former = Teacher::factory()->create();
        ClassTeacher::factory()->for($this->class)->for($former)->ended()->create();
        $this->record($former->user, $sheet)->assertForbidden();

        $this->record(User::factory()->withRole(RoleName::Admin)->create(), $sheet)->assertSessionHasNoErrors();
    }

    public function test_the_day_must_be_in_the_academic_year_and_not_in_the_future(): void
    {
        $this->record($this->adviser->user, $this->sheet(['present', 'present', 'present'], ['date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');

        $this->record($this->adviser->user, $this->sheet(['present', 'present', 'present'], ['date' => today()->subYear()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    public function test_only_students_in_the_class_can_be_marked(): void
    {
        $outsider = Student::factory()->create();
        $withdrawn = Student::factory()->create();
        Enrollment::factory()->for($withdrawn)->for($this->class)->withdrawn()->create();

        foreach ([$outsider, $withdrawn] as $student) {
            $this->record($this->adviser->user, [
                'date' => today()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'present']],
            ])->assertSessionHasErrors('records.0.student_id');
        }

        $this->record($this->adviser->user, $this->sheet(['present', 'present', 'present'], [
            'class_subject_id' => ClassSubject::factory()->create()->id,
        ]))->assertForbidden();
    }

    public function test_the_sheet_shows_what_was_recorded(): void
    {
        $this->record($this->adviser->user, $this->sheet(['present', 'absent', 'late']));

        $this->actingAs($this->adviser->user)
            ->get(route('attendance.sheet', $this->class))
            ->assertInertia(fn ($page) => $page
                ->component('Attendance/Sheet')
                ->has('rows', 3)
                ->where('recorded', true)
                ->where('can.record', true)
                ->where('rows', fn ($rows) => collect($rows)->pluck('status')->sort()->values()->all() === ['absent', 'late', 'present'])
            );

        // A subject teacher may look at the daily sheet but not change it.
        $classSubject = ClassSubject::factory()->create(['school_class_id' => $this->class->id]);
        $this->actingAs($classSubject->teacher->user)
            ->get(route('attendance.sheet', $this->class))
            ->assertInertia(fn ($page) => $page->where('can.record', false));
    }

    public function test_the_summary_counts_each_status_and_leaves_excused_days_out_of_the_percentage(): void
    {
        $student = $this->students[0];
        $statuses = ['present', 'present', 'present', 'late', 'absent', 'excused'];

        foreach ($statuses as $daysAgo => $status) {
            Attendance::factory()->for($this->class)->for($student)->status(AttendanceStatus::from($status))
                ->create(['attendance_date' => today()->subDays($daysAgo)]);
        }

        $this->actingAs($this->adviser->user)
            ->get(route('attendance.summary', [$this->class, 'from' => today()->subDays(10)->toDateString()]))
            ->assertInertia(fn ($page) => $page
                ->component('Attendance/Summary')
                ->where('days', 6)
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('student.id', $student->id)['summary'] === [
                    'present' => 3, 'absent' => 1, 'late' => 1, 'excused' => 1, 'total' => 6, 'percentage' => 80,
                ])
                ->where('overall.percentage', 80)
            );
    }

    public function test_students_see_their_own_attendance_only(): void
    {
        [$me, $classmate] = $this->students;
        Attendance::factory()->for($this->class)->for($me)->status(AttendanceStatus::Late)->create();

        $this->actingAs($me->user)->get(route('attendance.index'))->assertRedirect(route('attendance.student', $me));

        $this->actingAs($me->user)
            ->get(route('attendance.student', $me))
            ->assertInertia(fn ($page) => $page
                ->component('Attendance/Student')
                ->where('isOwn', true)
                ->where('summary.late', 1)
                ->where('summary.percentage', 100)
                ->has('history.data', 1)
            );

        $this->actingAs($me->user)->get(route('attendance.student', $classmate))->assertForbidden();
        $this->actingAs($me->user)->get(route('attendance.sheet', $this->class))->assertForbidden();
        $this->actingAs($me->user)->get(route('attendance.summary', $this->class))->assertForbidden();
    }

    public function test_teachers_see_attendance_of_students_in_their_classes_only(): void
    {
        $this->actingAs($this->adviser->user)->get(route('attendance.student', $this->students[0]))->assertOk();
        $this->actingAs($this->adviser->user)->get(route('attendance.student', Student::factory()->create()))->assertForbidden();
    }

    public function test_the_attendance_page_lists_the_teachers_classes(): void
    {
        SchoolClass::factory()->for($this->class->academicYear)->create();

        $this->actingAs($this->adviser->user)
            ->get(route('attendance.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Attendance/Index')
                ->has('classes', 1)
                ->where('classes.0.can_record', true)
                ->where('classes.0.students_count', 3)
            );
    }
}
