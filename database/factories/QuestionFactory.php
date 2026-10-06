<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\QuestionType;
use App\Models\LearningActivity;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * An essay question on a quiz. Use the type states for questions with options.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_activity_id' => LearningActivity::factory()->ofType(ActivityType::Quiz),
            'question' => rtrim(fake()->sentence(), '.').'?',
            'question_type' => QuestionType::Essay,
            'points' => 5,
            'order' => fn (array $attributes) => Question::where('learning_activity_id', $attributes['learning_activity_id'])->max('order') + 1,
        ];
    }

    /**
     * Multiple choice with four options; the first is correct.
     */
    public function multipleChoice(): static
    {
        return $this->withOptions(QuestionType::MultipleChoice, ['Right' => true, 'Wrong A' => false, 'Wrong B' => false, 'Wrong C' => false]);
    }

    /**
     * True or false, where the answer is $answer.
     */
    public function trueFalse(bool $answer = true): static
    {
        return $this->withOptions(QuestionType::TrueFalse, ['True' => $answer, 'False' => ! $answer]);
    }

    /**
     * Multiple select; "A" and "B" are correct.
     */
    public function multipleSelect(): static
    {
        return $this->withOptions(QuestionType::MultipleSelect, ['A' => true, 'B' => true, 'C' => false]);
    }

    /**
     * @param  list<string>  $answers
     */
    public function identification(array $answers = ['Manila']): static
    {
        return $this->withOptions(QuestionType::Identification, array_fill_keys($answers, true));
    }

    public function shortAnswer(): static
    {
        return $this->state(fn () => ['question_type' => QuestionType::ShortAnswer]);
    }

    /**
     * @param  array<string, bool>  $options  option text => is correct
     */
    private function withOptions(QuestionType $type, array $options): static
    {
        return $this->state(fn () => ['question_type' => $type])
            ->afterCreating(function (Question $question) use ($options): void {
                $order = 0;

                foreach ($options as $text => $correct) {
                    $question->options()->forceCreate(['option_text' => (string) $text, 'is_correct' => $correct, 'order' => ++$order]);
                }
            });
    }
}
