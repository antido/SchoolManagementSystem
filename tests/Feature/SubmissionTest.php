<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\SubmissionStatus;
use App\Models\ActivitySubmission;
use App\Models\Attachment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->classSubject = ClassSubject::factory()->create();
        $this->student = $this->enrolledStudent();
    }

    private function enrolledStudent(): Student
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $this->classSubject->school_class_id]);

        return $student;
    }

    private function activity(array $attributes = []): LearningActivity
    {
        return LearningActivity::factory()->for($this->classSubject)->published()->create($attributes);
    }

    private function teacher(): Teacher
    {
        return $this->classSubject->teacher;
    }

    public function test_an_enrolled_student_hands_in_work_before_the_deadline(): void
    {
        $activity = $this->activity();

        $this->actingAs($this->student->user)
            ->get(route('activities.show', $activity))
            ->assertInertia(fn ($page) => $page
                ->where('myStatus', 'pending')
                ->where('mySubmission', null)
                ->where('can.submit', true)
                ->missing('roster')
            );

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), ['body' => 'My answer'])
            ->assertRedirect(route('activities.show', $activity));

        $submission = ActivitySubmission::sole();
        $this->assertSame('My answer', $submission->body);
        $this->assertFalse($submission->is_late);
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);

        $this->actingAs($this->teacher()->user)
            ->get(route('activities.show', $activity))
            ->assertInertia(fn ($page) => $page
                ->has('roster', 1)
                ->where('roster.0.status', 'submitted')
                ->where('roster.0.submission_id', $submission->id)
            );
    }

    public function test_work_handed_in_after_the_deadline_is_marked_late(): void
    {
        $activity = $this->activity(['due_at' => now()->subHour()]);

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), ['body' => 'Sorry it is late'])
            ->assertSessionHas('success');

        $this->assertTrue(ActivitySubmission::sole()->is_late);

        $this->actingAs($this->student->user)
            ->get(route('activities.show', $activity))
            ->assertInertia(fn ($page) => $page->where('myStatus', 'late'));
    }

    public function test_late_work_is_refused_when_the_teacher_does_not_accept_it(): void
    {
        $activity = $this->activity(['due_at' => now()->subHour(), 'allow_late_submissions' => false]);

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), ['body' => 'Too late'])
            ->assertForbidden();

        $this->actingAs($this->student->user)
            ->get(route('activities.show', $activity))
            ->assertInertia(fn ($page) => $page->where('myStatus', 'missing')->where('can.submit', false));
    }

    public function test_work_cannot_be_handed_in_when_the_activity_is_not_open(): void
    {
        $closed = LearningActivity::factory()->for($this->classSubject)->closed()->create();
        $draft = LearningActivity::factory()->for($this->classSubject)->create();
        $notStarted = $this->activity(['start_at' => now()->addDay()]);
        $quiz = LearningActivity::factory()->for($this->classSubject)->published()->ofType(ActivityType::Quiz)->create();

        foreach ([$closed, $draft, $notStarted, $quiz] as $activity) {
            $this->actingAs($this->student->user)
                ->post(route('submissions.store', $activity), ['body' => 'Answer'])
                ->assertForbidden();
        }
    }

    public function test_students_outside_the_class_and_teachers_cannot_hand_in_work(): void
    {
        $activity = $this->activity();

        $this->actingAs(Student::factory()->create()->user)
            ->post(route('submissions.store', $activity), ['body' => 'Answer'])
            ->assertForbidden();

        $this->actingAs($this->teacher()->user)
            ->post(route('submissions.store', $activity), ['body' => 'Answer'])
            ->assertForbidden();
    }

    public function test_work_needs_an_answer_or_a_file(): void
    {
        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $this->activity()), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_files_are_stored_privately_and_only_downloaded_by_people_who_may_see_them(): void
    {
        $activity = $this->activity();

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), [
                'attachments' => [UploadedFile::fake()->create('essay.pdf', 200, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::sole();
        $this->assertSame('essay.pdf', $attachment->original_name);
        $this->assertStringStartsWith("submissions/{$activity->id}/", $attachment->path);
        $this->assertStringNotContainsString('essay', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->student->user)->get(route('attachments.show', $attachment))->assertOk()->assertDownload('essay.pdf');
        $this->actingAs($this->teacher()->user)->get(route('attachments.show', $attachment))->assertOk();

        // A classmate and an unrelated teacher may not.
        $this->actingAs($this->enrolledStudent()->user)->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs(Teacher::factory()->create()->user)->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_disallowed_and_oversized_files_are_rejected(): void
    {
        $activity = $this->activity();

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), ['attachments' => [UploadedFile::fake()->create('run.php', 1, 'text/x-php')]])
            ->assertSessionHasErrors('attachments.0');

        $this->actingAs($this->student->user)
            ->post(route('submissions.store', $activity), [
                'attachments' => [UploadedFile::fake()->create('huge.pdf', config('school.attachments.max_kilobytes') + 1, 'application/pdf')],
            ])
            ->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_handing_in_again_replaces_the_work_until_it_is_graded(): void
    {
        $activity = $this->activity();
        $user = $this->student->user;

        $this->actingAs($user)->post(route('submissions.store', $activity), [
            'body' => 'First try',
            'attachments' => [UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')],
        ]);
        $first = Attachment::sole();

        $this->actingAs($user)->post(route('submissions.store', $activity), [
            'body' => 'Second try',
            'remove_attachments' => [$first->id],
        ])->assertSessionHasNoErrors();

        $submission = ActivitySubmission::sole();
        $this->assertSame('Second try', $submission->body);
        $this->assertModelMissing($first);
        Storage::disk('local')->assertMissing($first->path);

        $submission->forceFill(['status' => SubmissionStatus::Graded, 'score' => 50])->save();

        $this->actingAs($user)->post(route('submissions.store', $activity), ['body' => 'Third try'])->assertForbidden();
    }

    public function test_the_teacher_grades_work_and_the_student_sees_the_feedback(): void
    {
        $activity = $this->activity(['total_points' => 20]);
        $submission = ActivitySubmission::factory()->for($activity)->for($this->student)->create();

        $this->actingAs($this->teacher()->user)
            ->patch(route('submissions.grade', $submission), ['score' => 25])
            ->assertSessionHasErrors('score');

        $this->actingAs($this->teacher()->user)
            ->patch(route('submissions.grade', $submission), ['score' => 18.5, 'feedback' => 'Well argued.'])
            ->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Graded, $submission->status);
        $this->assertSame('18.50', $submission->score);
        $this->assertSame($this->teacher()->user_id, $submission->graded_by);

        $this->actingAs($this->student->user)
            ->get(route('activities.show', $activity))
            ->assertInertia(fn ($page) => $page
                ->where('myStatus', 'graded')
                ->where('mySubmission.score', 18.5)
                ->where('mySubmission.feedback', 'Well argued.')
                ->where('can.submit', false)
            );
    }

    public function test_only_the_class_subjects_teacher_or_an_admin_can_grade(): void
    {
        $submission = ActivitySubmission::factory()->for($this->activity())->for($this->student)->create();

        $this->actingAs(Teacher::factory()->create()->user)
            ->patch(route('submissions.grade', $submission), ['score' => 10])
            ->assertForbidden();

        $this->actingAs($this->student->user)
            ->patch(route('submissions.grade', $submission), ['score' => 100])
            ->assertForbidden();
    }

    public function test_submission_pages_are_limited_to_the_student_and_their_teachers(): void
    {
        $submission = ActivitySubmission::factory()->for($this->activity())->for($this->student)->create();

        $this->actingAs($this->teacher()->user)
            ->get(route('submissions.show', $submission))
            ->assertInertia(fn ($page) => $page->component('Submissions/Show')->where('can.grade', true));

        $this->actingAs($this->student->user)
            ->get(route('submissions.show', $submission))
            ->assertInertia(fn ($page) => $page->where('can.grade', false));

        $this->actingAs($this->enrolledStudent()->user)->get(route('submissions.show', $submission))->assertForbidden();
    }

    public function test_teachers_attach_files_to_activities_and_remove_them(): void
    {
        $user = $this->teacher()->user;

        $this->actingAs($user)->post(route('activities.store'), [
            'class_subject_id' => $this->classSubject->id,
            'type' => 'assignment',
            'title' => 'Reading',
            'total_points' => 10,
            'attachments' => [UploadedFile::fake()->create('chapter.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $activity = LearningActivity::sole();
        $file = Attachment::sole();
        $this->assertStringStartsWith("assignments/{$activity->id}/", $file->path);

        // Edits arrive as POST with _method=PUT so files can be sent.
        $this->actingAs($user)->post(route('activities.update', $activity), [
            '_method' => 'put',
            'type' => 'assignment',
            'title' => 'Reading',
            'total_points' => 10,
            'remove_attachments' => [$file->id],
            'attachments' => [UploadedFile::fake()->create('chapter-2.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
        $this->assertSame('chapter-2.pdf', $activity->attachments()->sole()->original_name);

        $remaining = $activity->attachments()->sole();
        $this->actingAs($user)->delete(route('activities.destroy', $activity));
        $this->assertModelMissing($activity);
        Storage::disk('local')->assertMissing($remaining->path);
    }

    public function test_students_cannot_download_files_of_activities_they_cannot_see(): void
    {
        $draft = LearningActivity::factory()->for($this->classSubject)->create();
        $this->actingAs($this->teacher()->user)->post(route('activities.update', $draft), [
            '_method' => 'put',
            'type' => 'assignment',
            'title' => $draft->title,
            'total_points' => 10,
            'attachments' => [UploadedFile::fake()->create('answers.pdf', 5, 'application/pdf')],
        ]);

        $this->actingAs($this->student->user)
            ->get(route('attachments.show', Attachment::sole()))
            ->assertForbidden();
    }

    public function test_activities_with_submissions_are_kept(): void
    {
        $activity = $this->activity();
        ActivitySubmission::factory()->for($activity)->for($this->student)->create();

        $this->actingAs($this->teacher()->user)
            ->delete(route('activities.destroy', $activity))
            ->assertSessionHas('error');

        $this->assertModelExists($activity);
    }

    public function test_the_teacher_dashboard_counts_work_to_grade(): void
    {
        $activity = $this->activity();
        ActivitySubmission::factory()->for($activity)->for($this->student)->create();
        ActivitySubmission::factory()->for($activity)->for($this->enrolledStudent())->graded()->create();

        $this->actingAs($this->teacher()->user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.3.label', 'Work to grade')->where('stats.3.value', 1));
    }
}
