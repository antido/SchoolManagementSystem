<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\QuestionType;
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

class QuestionTest extends TestCase
{
    use RefreshDatabase;

    private LearningActivity $quiz;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quiz = LearningActivity::factory()->ofType(ActivityType::Quiz)->create(['total_points' => 0]);
        $this->teacher = $this->quiz->classSubject->teacher->user;
    }

    private function add(array $data): TestResponse
    {
        return $this->actingAs($this->teacher)->post(route('questions.store', $this->quiz), [
            'question' => 'What is 2 + 2?',
            'points' => 2,
            ...$data,
        ]);
    }

    public function test_a_teacher_adds_a_multiple_choice_question(): void
    {
        $this->add([
            'question_type' => 'multiple_choice',
            'options' => [
                ['text' => '3', 'is_correct' => false],
                ['text' => '4', 'is_correct' => true],
                ['text' => '5', 'is_correct' => false],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $question = Question::sole();
        $this->assertSame(QuestionType::MultipleChoice, $question->question_type);
        $this->assertSame(1, $question->order);
        $this->assertSame(['3', '4', '5'], $question->options->pluck('option_text')->all());
        $this->assertSame('4', $question->options->firstWhere('is_correct', true)->option_text);
        $this->assertSame('2.00', $this->quiz->fresh()->total_points);
    }

    public function test_multiple_choice_needs_two_options_and_exactly_one_correct_answer(): void
    {
        $this->add(['question_type' => 'multiple_choice', 'options' => [['text' => 'Only', 'is_correct' => true]]])
            ->assertSessionHasErrors('options');

        $this->add(['question_type' => 'multiple_choice', 'options' => [
            ['text' => 'A', 'is_correct' => true],
            ['text' => 'B', 'is_correct' => true],
        ]])->assertSessionHasErrors('options');

        $this->add(['question_type' => 'multiple_choice', 'options' => [
            ['text' => 'A', 'is_correct' => false],
            ['text' => 'B', 'is_correct' => false],
        ]])->assertSessionHasErrors('options');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_multiple_select_allows_several_correct_answers_but_needs_one(): void
    {
        $this->add(['question_type' => 'multiple_select', 'options' => [
            ['text' => 'A', 'is_correct' => false],
            ['text' => 'B', 'is_correct' => false],
        ]])->assertSessionHasErrors('options');

        $this->add(['question_type' => 'multiple_select', 'options' => [
            ['text' => 'A', 'is_correct' => true],
            ['text' => 'B', 'is_correct' => true],
            ['text' => 'C', 'is_correct' => false],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2, Question::sole()->options()->where('is_correct', true)->count());
    }

    public function test_true_or_false_questions_get_their_two_options(): void
    {
        $this->add(['question_type' => 'true_false'])->assertSessionHasErrors('correct_answer');

        $this->add(['question_type' => 'true_false', 'correct_answer' => 'false'])->assertSessionHasNoErrors();

        $options = Question::sole()->options;
        $this->assertSame(['True', 'False'], $options->pluck('option_text')->all());
        $this->assertSame([false, true], $options->pluck('is_correct')->all());
    }

    public function test_identification_needs_accepted_answers_and_marks_them_all_correct(): void
    {
        $this->add(['question_type' => 'identification', 'options' => []])->assertSessionHasErrors('options');

        $this->add(['question_type' => 'identification', 'options' => [
            ['text' => 'Jose Rizal'],
            ['text' => 'Rizal'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame([true, true], Question::sole()->options->pluck('is_correct')->all());
    }

    public function test_essay_and_short_answer_questions_keep_no_options(): void
    {
        $this->add(['question_type' => 'essay', 'options' => [['text' => 'ignored', 'is_correct' => true]]])->assertSessionHasNoErrors();

        $this->assertSame(0, Question::sole()->options()->count());
    }

    public function test_editing_replaces_options_and_updates_the_total(): void
    {
        $question = Question::factory()->for($this->quiz)->multipleChoice()->create(['points' => 1]);

        $this->actingAs($this->teacher)->put(route('questions.update', $question), [
            'question' => 'Capital of Japan?',
            'question_type' => 'identification',
            'points' => 3,
            'options' => [['text' => 'Tokyo']],
        ])->assertSessionHasNoErrors();

        $question->refresh();
        $this->assertSame(QuestionType::Identification, $question->question_type);
        $this->assertSame(['Tokyo'], $question->options->pluck('option_text')->all());
        $this->assertSame('3.00', $this->quiz->fresh()->total_points);
    }

    public function test_questions_can_be_reordered_and_removed(): void
    {
        [$first, $second, $third] = Question::factory()->for($this->quiz)->count(3)->create()->all();

        $this->actingAs($this->teacher)->patch(route('questions.move', $third), ['direction' => 'up']);
        $this->assertSame([$first->id, $third->id, $second->id], $this->quiz->questions()->pluck('id')->all());

        // Moving the first question up does nothing.
        $this->actingAs($this->teacher)->patch(route('questions.move', $first), ['direction' => 'up']);
        $this->assertSame($first->id, $this->quiz->questions()->first()->id);

        $this->actingAs($this->teacher)->delete(route('questions.destroy', $first))->assertSessionHas('success');
        $this->assertSame([1, 2], $this->quiz->questions()->pluck('order')->all());
        $this->assertSame('10.00', $this->quiz->fresh()->total_points);
    }

    public function test_questions_are_copied_from_another_activity_the_teacher_manages(): void
    {
        $source = LearningActivity::factory()->ofType(ActivityType::Examination)->create([
            'class_subject_id' => ClassSubject::factory()->create(['teacher_id' => $this->quiz->classSubject->teacher_id])->id,
        ]);
        Question::factory()->for($source)->multipleChoice()->create(['points' => 4]);
        Question::factory()->for($source)->create(['points' => 6]);
        Question::factory()->for($this->quiz)->create(['points' => 1]);

        $this->actingAs($this->teacher)
            ->get(route('activities.show', $this->quiz))
            ->assertInertia(fn ($page) => $page->has('copySources', 1)->where('copySources.0.value', $source->id));

        $this->actingAs($this->teacher)
            ->post(route('questions.copy', $this->quiz), ['source_activity_id' => $source->id])
            ->assertSessionHas('success', '2 questions were copied.');

        $copies = $this->quiz->questions()->with('options')->get();
        $this->assertCount(3, $copies);
        $this->assertSame([1, 2, 3], $copies->pluck('order')->all());
        $this->assertCount(4, $copies[1]->options);
        $this->assertSame('11.00', $this->quiz->fresh()->total_points);
        $this->assertSame(2, $source->questions()->count());
    }

    public function test_questions_cannot_be_copied_from_someone_elses_activity(): void
    {
        $other = Question::factory()->create();

        $this->actingAs($this->teacher)
            ->post(route('questions.copy', $this->quiz), ['source_activity_id' => $other->learning_activity_id])
            ->assertForbidden();
    }

    public function test_other_teachers_and_students_cannot_change_questions_or_see_answers(): void
    {
        $question = Question::factory()->for($this->quiz)->multipleChoice()->create();
        $otherTeacher = Teacher::factory()->create()->user;

        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $this->quiz->classSubject->school_class_id]);
        $this->quiz->forceFill(['status' => 'published'])->save();

        foreach ([$otherTeacher, $student->user] as $user) {
            $this->actingAs($user)->post(route('questions.store', $this->quiz), ['question' => 'X', 'question_type' => 'essay', 'points' => 1])->assertForbidden();
            $this->actingAs($user)->delete(route('questions.destroy', $question))->assertForbidden();
        }

        $this->actingAs($student->user)
            ->get(route('activities.show', $this->quiz))
            ->assertInertia(fn ($page) => $page->missing('questions'));
    }

    public function test_assignments_do_not_take_questions(): void
    {
        $assignment = LearningActivity::factory()->create();

        $this->actingAs($assignment->classSubject->teacher->user)
            ->post(route('questions.store', $assignment), ['question' => 'X', 'question_type' => 'essay', 'points' => 1])
            ->assertForbidden();
    }

    public function test_the_teacher_sees_questions_with_answers_on_the_activity_page(): void
    {
        Question::factory()->for($this->quiz)->trueFalse(false)->create();

        $this->actingAs($this->teacher)
            ->get(route('activities.show', $this->quiz))
            ->assertInertia(fn ($page) => $page
                ->has('questions', 1)
                ->where('questions.0.options.1.is_correct', true)
                ->where('canChangeQuestions', true)
            );
    }

    public function test_an_activity_with_questions_cannot_become_an_assignment(): void
    {
        Question::factory()->for($this->quiz)->create();

        $this->actingAs($this->teacher)->put(route('activities.update', $this->quiz), [
            'type' => 'assignment',
            'title' => $this->quiz->title,
            'total_points' => 10,
        ])->assertSessionHasErrors('type');
    }

    public function test_quiz_points_come_from_the_questions_not_the_form(): void
    {
        Question::factory()->for($this->quiz)->create(['points' => 7]);

        $this->actingAs($this->teacher)->put(route('activities.update', $this->quiz), [
            'type' => 'quiz',
            'title' => 'Renamed quiz',
            'total_points' => 999,
        ])->assertSessionHasNoErrors();

        $this->assertSame('7.00', $this->quiz->fresh()->total_points);
    }
}
