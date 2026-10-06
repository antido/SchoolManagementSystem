<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Events\AwardGranted;
use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\Award;
use App\Models\ClassTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AwardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    /**
     * A teacher advising a class with one student in it.
     *
     * @return array{0: Teacher, 1: Student, 2: SchoolClass}
     */
    private function teacherWithStudent(): array
    {
        $class = SchoolClass::factory()->create();
        $teacher = Teacher::factory()->create();
        ClassTeacher::factory()->for($class)->for($teacher)->adviser()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for($class)->create();

        return [$teacher, $student, $class];
    }

    public function test_admins_create_awards_with_an_optional_picture(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->post(route('awards.store'), [
            'name' => 'Academic Excellence',
            'description' => 'For the best grades.',
            'criteria' => 'An average of 90% or more.',
            'status' => 'active',
            'image' => UploadedFile::fake()->image('medal.png', 200, 200),
        ])->assertRedirect();

        $award = Award::sole();
        $image = Attachment::sole();
        $this->assertTrue($image->attachable->is($award));
        $this->assertStringStartsWith('awards/', $image->path);

        // Shown in the page rather than downloaded.
        $this->actingAs(Student::factory()->create()->user)
            ->get(route('attachments.show', [$image, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_award_pictures_must_be_images(): void
    {
        $this->actingAs($this->admin())->post(route('awards.store'), [
            'name' => 'Top Performer',
            'status' => 'active',
            'image' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');
    }

    public function test_teachers_and_students_cannot_manage_awards(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();

        foreach ([$teacher->user, $student->user] as $user) {
            $this->actingAs($user)->post(route('awards.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        }
    }

    public function test_a_teacher_gives_an_award_to_a_student_in_their_class(): void
    {
        Event::fake([AwardGranted::class]);
        [$teacher, $student, $class] = $this->teacherWithStudent();
        $award = Award::factory()->create();

        $this->actingAs($teacher->user)->post(route('awards.grant', $award), [
            'student_id' => $student->id,
            'academic_year_id' => $class->academic_year_id,
            'school_class_id' => $class->id,
            'awarded_on' => today()->toDateString(),
            'remarks' => 'Well done!',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $given = StudentAward::sole();
        $this->assertSame($student->id, $given->student_id);
        $this->assertSame($class->id, $given->school_class_id);
        $this->assertSame($teacher->user_id, $given->awarded_by);
        Event::assertDispatched(AwardGranted::class, fn (AwardGranted $event) => $event->studentAward->is($given));
    }

    public function test_teachers_cannot_give_awards_to_students_outside_their_classes(): void
    {
        [$teacher] = $this->teacherWithStudent();
        $award = Award::factory()->create();

        $this->actingAs($teacher->user)->post(route('awards.grant', $award), [
            'student_id' => Student::factory()->create()->id,
            'awarded_on' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_inactive_awards_cannot_be_given(): void
    {
        [, $student] = $this->teacherWithStudent();

        $this->actingAs($this->admin())->post(route('awards.grant', Award::factory()->inactive()->create()), [
            'student_id' => $student->id,
            'awarded_on' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_the_class_must_be_one_of_the_students_classes_and_the_date_not_in_the_future(): void
    {
        [, $student] = $this->teacherWithStudent();
        $award = Award::factory()->create();

        $this->actingAs($this->admin())->post(route('awards.grant', $award), [
            'student_id' => $student->id,
            'school_class_id' => SchoolClass::factory()->create()->id,
            'awarded_on' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors(['school_class_id', 'awarded_on']);
    }

    public function test_revoked_awards_stay_in_the_history(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();
        $given = StudentAward::factory()->for($student)->create(['awarded_by' => $teacher->user_id]);

        $this->actingAs($teacher->user)
            ->patch(route('awards.revoke', $given), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($teacher->user)
            ->patch(route('awards.revoke', $given), ['reason' => 'Given to the wrong student.'])
            ->assertSessionHas('success');

        $given->refresh();
        $this->assertNotNull($given->revoked_at);
        $this->assertSame('Given to the wrong student.', $given->revoked_reason);
        $this->assertModelExists($given);

        // Revoked awards are hidden from the student but kept for staff.
        $this->actingAs($student->user)
            ->get(route('students.show', $student))
            ->assertInertia(fn ($page) => $page->has('awards', 0));
        $this->actingAs($this->admin())
            ->get(route('students.show', $student))
            ->assertInertia(fn ($page) => $page->has('awards', 1)->whereNot('awards.0.revoked_at', null));
    }

    public function test_only_admins_or_the_teacher_who_gave_it_can_revoke(): void
    {
        [$teacher, $student, $class] = $this->teacherWithStudent();
        $colleague = Teacher::factory()->create();
        ClassTeacher::factory()->for($class)->for($colleague)->create();
        $given = StudentAward::factory()->for($student)->create(['awarded_by' => $teacher->user_id]);

        $this->actingAs($colleague->user)->patch(route('awards.revoke', $given), ['reason' => 'No'])->assertForbidden();
        $this->actingAs($this->admin())->patch(route('awards.revoke', $given), ['reason' => 'Mistake'])->assertSessionHas('success');
    }

    public function test_students_see_the_awards_they_received(): void
    {
        $student = Student::factory()->create();
        StudentAward::factory()->for($student)->count(2)->create();
        StudentAward::factory()->for($student)->revoked()->create();
        StudentAward::factory()->create();

        $this->actingAs($student->user)
            ->get(route('awards.index'))
            ->assertInertia(fn ($page) => $page->component('Awards/Index')->has('myAwards', 2)->where('can.create', false));

        $this->actingAs($student->user)
            ->get(route('students.show', $student))
            ->assertInertia(fn ($page) => $page->has('awards', 2));
    }

    public function test_students_see_only_their_own_entry_on_an_award_page(): void
    {
        $student = Student::factory()->create();
        $award = Award::factory()->create();
        StudentAward::factory()->for($award)->for($student)->create();
        StudentAward::factory()->for($award)->count(3)->create();

        $this->actingAs($student->user)
            ->get(route('awards.show', $award))
            ->assertInertia(fn ($page) => $page->has('recipients', 1)->where('can.grant', false));
    }

    public function test_the_grant_dialog_searches_only_students_the_person_may_award(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();
        $student->update(['last_name' => 'Zabala']);
        $other = Student::factory()->create(['last_name' => 'Zabalo']);
        $award = Award::factory()->create();

        $this->actingAs($teacher->user)
            ->get(route('awards.show', [$award, 'candidate_search' => 'Zab']))
            ->assertInertia(fn ($page) => $page
                ->reloadOnly('candidates', fn ($reload) => $reload
                    ->has('candidates', 1)
                    ->where('candidates.0.id', $student->id)
                    ->has('candidates.0.classes', 1)
                )
            );

        $this->assertNotSame($other->id, $student->id);
    }

    public function test_awards_that_were_given_are_kept_and_students_with_awards_are_not_deleted(): void
    {
        $admin = $this->admin();
        $given = StudentAward::factory()->create();

        $this->actingAs($admin)->delete(route('awards.destroy', $given->award))->assertSessionHas('error');
        $this->assertModelExists($given->award);

        $this->actingAs($admin)->delete(route('students.destroy', $given->student))->assertSessionHas('error');
        $this->assertModelExists($given->student);

        $unused = Award::factory()->create();
        $this->actingAs($admin)->delete(route('awards.destroy', $unused))->assertRedirect(route('awards.index'));
        $this->assertModelMissing($unused);
    }

    public function test_the_award_date_defaults_can_include_the_active_year(): void
    {
        $year = AcademicYear::factory()->active()->create();

        $this->actingAs($this->admin())
            ->get(route('awards.show', Award::factory()->create()))
            ->assertInertia(fn ($page) => $page->where('activeYearId', $year->id)->where('can.grant', true));
    }
}
