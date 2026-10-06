<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\AttemptStatus;
use App\Models\ActivityAttempt;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    private LearningActivity $exam;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $classSubject = ClassSubject::factory()->create();
        $this->exam = LearningActivity::factory()->for($classSubject)->ofType(ActivityType::Examination)->published()->create([
            'duration_minutes' => 30,
            'due_at' => now()->addDay(),
        ]);

        $this->student = Student::factory()->create();
        Enrollment::factory()->for($this->student)->create(['school_class_id' => $classSubject->school_class_id]);
    }

    private function question(string $state = 'multipleChoice', array $attributes = []): Question
    {
        return Question::factory()->for($this->exam)->{$state}()->create(['points' => 2, ...$attributes]);
    }

    private function start(?Student $student = null): ActivityAttempt
    {
        $student ??= $this->student;
        $this->actingAs($student->user)->post(route('attempts.store', $this->exam))->assertRedirect();

        return ActivityAttempt::query()->where('student_id', $student->id)->latest('id')->firstOrFail();
    }

    private function answer(ActivityAttempt $attempt, Question $question, array $data): TestResponse
    {
        return $this->actingAs($this->student->user)
            ->putJson(route('attempts.answer', $attempt), ['question_id' => $question->id, ...$data]);
    }

    private function teacherUser(): User
    {
        return $this->exam->classSubject->teacher->user;
    }

    public function test_a_student_starts_an_exam_and_gets_the_questions_without_answers(): void
    {
        $this->question();

        $attempt = $this->start();

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $attempt->expires_at->timestamp, 2);

        $this->actingAs($this->student->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->component('Attempts/Take')
                ->has('questions', 1)
                ->has('questions.0.options', 4)
                ->missing('questions.0.options.0.is_correct')
                ->where('attempt.seconds_remaining', fn ($seconds) => $seconds > 1790 && $seconds <= 1800)
            );
    }

    public function test_the_due_date_cuts_the_time_limit_short(): void
    {
        $this->question();
        $this->exam->update(['due_at' => now()->addMinutes(10)]);

        $attempt = $this->start();

        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $attempt->expires_at->timestamp, 2);
    }

    public function test_objective_answers_are_scored_when_the_attempt_is_handed_in(): void
    {
        $choice = $this->question('multipleChoice');
        $trueFalse = $this->question('trueFalse');
        $select = $this->question('multipleSelect');
        $identification = Question::factory()->for($this->exam)->identification(['Jose Rizal'])->create(['points' => 2]);
        $essay = $this->question('shortAnswer', ['points' => 5]);
        $skipped = $this->question('multipleChoice');

        $attempt = $this->start();

        $this->answer($attempt, $choice, ['selected_options' => [$choice->options->firstWhere('is_correct', true)->id]])->assertOk();
        $this->answer($attempt, $trueFalse, ['selected_options' => [$trueFalse->options->firstWhere('is_correct', false)->id]])->assertOk();
        // Only one of the two correct options: no partial credit.
        $this->answer($attempt, $select, ['selected_options' => [$select->options->firstWhere('option_text', 'A')->id]])->assertOk();
        $this->answer($attempt, $identification, ['answer_text' => '  jose   RIZAL '])->assertOk();
        $this->answer($attempt, $essay, ['answer_text' => 'Because of photosynthesis.'])->assertOk();

        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt))->assertRedirect(route('attempts.show', $attempt));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertSame('4.00', $attempt->score);

        $answers = $attempt->answers()->get()->keyBy('question_id');
        $this->assertTrue($answers[$choice->id]->is_correct);
        $this->assertFalse($answers[$trueFalse->id]->is_correct);
        $this->assertFalse($answers[$select->id]->is_correct);
        $this->assertTrue($answers[$identification->id]->is_correct);
        $this->assertNull($answers[$essay->id]->points_awarded);
        $this->assertSame('0.00', $answers[$skipped->id]->points_awarded);
    }

    public function test_an_attempt_with_only_objective_questions_is_graded_at_once(): void
    {
        $question = $this->question();
        $attempt = $this->start();
        $this->answer($attempt, $question, ['selected_options' => [$question->options->firstWhere('is_correct', true)->id]]);

        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        $this->assertSame(AttemptStatus::Graded, $attempt->fresh()->status);
        $this->assertSame('2.00', $attempt->fresh()->score);
    }

    public function test_the_teacher_scores_written_answers(): void
    {
        $choice = $this->question();
        $essay = $this->question('shortAnswer', ['points' => 5]);
        $attempt = $this->start();
        $this->answer($attempt, $choice, ['selected_options' => [$choice->options->firstWhere('is_correct', true)->id]]);
        $this->answer($attempt, $essay, ['answer_text' => 'An answer']);
        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        $essayAnswer = $attempt->answers()->where('question_id', $essay->id)->sole();

        $this->actingAs($this->teacherUser())
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->component('Attempts/Show')->where('can.grade', true)->where('showAnswers', true));

        $this->actingAs($this->teacherUser())
            ->patch(route('attempts.grade', $attempt), ['scores' => [$essayAnswer->id => ['points' => 6]]])
            ->assertSessionHasErrors("scores.{$essayAnswer->id}.points");

        $this->actingAs($this->teacherUser())
            ->patch(route('attempts.grade', $attempt), ['scores' => [$essayAnswer->id => ['points' => 3.5, 'feedback' => 'Explain more.']]])
            ->assertSessionHas('success');

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertSame('5.50', $attempt->score);
        $this->assertSame('Explain more.', $essayAnswer->fresh()->feedback);
    }

    public function test_answers_are_refused_after_time_runs_out_and_the_attempt_is_handed_in(): void
    {
        $question = $this->question();
        $attempt = $this->start();
        $this->answer($attempt, $question, ['selected_options' => [$question->options->firstWhere('is_correct', true)->id]])->assertOk();

        $this->travel(31)->minutes();

        $this->answer($attempt, $question, ['selected_options' => [$question->options->firstWhere('is_correct', false)->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attempt');

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertTrue($attempt->auto_submitted);
        // The answer saved in time counts; the late change does not.
        $this->assertSame('2.00', $attempt->score);
        $this->assertTrue($attempt->submitted_at->lte($attempt->expires_at));
    }

    public function test_a_short_grace_period_covers_the_last_autosave(): void
    {
        $question = $this->question();
        $attempt = $this->start();

        $this->travel(30)->minutes();
        $this->travel(5)->seconds();

        $this->answer($attempt, $question, ['selected_options' => [$question->options->first()->id]])->assertOk();
    }

    public function test_opening_an_expired_attempt_shows_the_results(): void
    {
        $this->question();
        $attempt = $this->start();
        $this->travel(2)->hours();

        $this->actingAs($this->student->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->component('Attempts/Show')->where('attempt.auto_submitted', true));
    }

    public function test_the_scheduled_command_hands_in_expired_attempts(): void
    {
        $this->question();
        $attempt = $this->start();
        $this->travel(31)->minutes();

        $this->artisan('attempts:close-expired')->expectsOutput('1 attempt(s) handed in.')->assertSuccessful();

        $this->assertSame(AttemptStatus::Graded, $attempt->fresh()->status);
    }

    public function test_starting_again_resumes_the_attempt_in_progress(): void
    {
        $this->question();
        $first = $this->start();
        $again = $this->start();

        $this->assertTrue($first->is($again));
        $this->assertSame(1, ActivityAttempt::count());
    }

    public function test_students_get_only_the_attempts_allowed(): void
    {
        $this->question();
        $attempt = $this->start();
        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        $this->actingAs($this->student->user)->post(route('attempts.store', $this->exam))->assertForbidden();

        $this->exam->update(['max_attempts' => 2]);
        $second = $this->start();
        $this->assertSame(2, $second->attempt_number);
    }

    public function test_exams_that_are_not_open_cannot_be_started(): void
    {
        $this->question();

        $cases = [
            ['status' => 'draft'],
            ['status' => 'closed'],
            ['start_at' => now()->addHour()],
            ['due_at' => now()->subMinute()],
        ];

        foreach ($cases as $attributes) {
            $this->exam->forceFill([...$attributes])->save();
            $this->actingAs($this->student->user)->post(route('attempts.store', $this->exam))->assertForbidden();
            $this->exam->forceFill(['status' => 'published', 'start_at' => null, 'due_at' => now()->addDay()])->save();
        }

        $this->assertSame(0, ActivityAttempt::count());
    }

    public function test_an_exam_without_questions_cannot_be_started(): void
    {
        $this->actingAs($this->student->user)->post(route('attempts.store', $this->exam))->assertForbidden();
    }

    public function test_students_outside_the_class_and_teachers_cannot_start(): void
    {
        $this->question();

        $this->actingAs(Student::factory()->create()->user)->post(route('attempts.store', $this->exam))->assertForbidden();
        $this->actingAs($this->teacherUser())->post(route('attempts.store', $this->exam))->assertForbidden();
    }

    public function test_answers_must_fit_the_question(): void
    {
        $question = $this->question();
        $other = Question::factory()->multipleChoice()->create();
        $attempt = $this->start();

        // An option of another question.
        $this->answer($attempt, $question, ['selected_options' => [$other->options->first()->id]])->assertJsonValidationErrors('selected_options');

        // Two options on a single-answer question.
        $this->answer($attempt, $question, ['selected_options' => $question->options->take(2)->pluck('id')->all()])
            ->assertJsonValidationErrors('selected_options');

        // A question that is not part of the attempt.
        $this->actingAs($this->student->user)
            ->putJson(route('attempts.answer', $attempt), ['question_id' => $other->id, 'selected_options' => [$other->options->first()->id]])
            ->assertJsonValidationErrors('question_id');
    }

    public function test_nobody_else_can_answer_or_see_an_attempt(): void
    {
        $question = $this->question();
        $attempt = $this->start();

        $classmate = Student::factory()->create();
        Enrollment::factory()->for($classmate)->create(['school_class_id' => $this->exam->classSubject->school_class_id]);

        $this->actingAs($classmate->user)
            ->putJson(route('attempts.answer', $attempt), ['question_id' => $question->id, 'selected_options' => [$question->options->first()->id]])
            ->assertForbidden();
        $this->actingAs($classmate->user)->get(route('attempts.show', $attempt))->assertForbidden();
        $this->actingAs($classmate->user)->post(route('attempts.submit', $attempt))->assertForbidden();
        $this->actingAs(Teacher::factory()->create()->user)->get(route('attempts.show', $attempt))->assertForbidden();
    }

    public function test_questions_are_locked_once_students_start(): void
    {
        $question = $this->question();
        $this->start();

        $this->actingAs($this->teacherUser())
            ->post(route('questions.store', $this->exam), ['question' => 'Late addition', 'question_type' => 'essay', 'points' => 1])
            ->assertForbidden();
        $this->actingAs($this->teacherUser())->delete(route('questions.destroy', $question))->assertForbidden();
    }

    public function test_closing_the_exam_hands_in_attempts_in_progress(): void
    {
        $this->question();
        $attempt = $this->start();

        $this->actingAs($this->teacherUser())->patch(route('activities.status', $this->exam), ['status' => 'closed']);

        $this->assertTrue($attempt->fresh()->auto_submitted);
        $this->assertNotSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_students_see_the_answer_key_only_after_the_exam_is_closed(): void
    {
        $this->question();
        $attempt = $this->start();
        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        $this->actingAs($this->student->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->where('showAnswers', false)->missing('items.0.question.options.0.is_correct'));

        $this->exam->forceFill(['status' => 'closed'])->save();

        $this->actingAs($this->student->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->where('showAnswers', true)->has('items.0.question.options.0.is_correct'));
    }

    public function test_shuffled_exams_still_contain_every_question(): void
    {
        $ids = collect(range(1, 6))->map(fn () => $this->question()->id)->all();
        $this->exam->update(['shuffle_questions' => true, 'shuffle_options' => true]);

        $attempt = $this->start();

        $this->assertEqualsCanonicalizing($ids, $attempt->question_order);
        $this->assertCount(6, $attempt->option_order);
    }

    public function test_the_activity_page_shows_attempts_to_students_and_results_to_teachers(): void
    {
        $this->question();
        $attempt = $this->start();
        $this->actingAs($this->student->user)->post(route('attempts.submit', $attempt));

        $this->actingAs($this->student->user)
            ->get(route('activities.show', $this->exam))
            ->assertInertia(fn ($page) => $page->has('myAttempts', 1)->where('attemptInProgress', null)->where('can.attempt', false));

        $this->actingAs($this->teacherUser())
            ->get(route('activities.show', $this->exam))
            ->assertInertia(fn ($page) => $page
                ->has('attemptRoster', 1)
                ->where('attemptRoster.0.attempts', 1)
                ->where('attemptRoster.0.latest_attempt_id', $attempt->id)
                ->where('questionsLocked', true)
            );
    }
}
