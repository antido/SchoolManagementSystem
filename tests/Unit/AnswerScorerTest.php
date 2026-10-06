<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AnswerScorer;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnswerScorerTest extends TestCase
{
    /**
     * A question in memory, no database: options are [id => [text, correct]].
     *
     * @param  array<int, array{0: string, 1: bool}>  $options
     */
    private function question(QuestionType $type, array $options, float $points = 4): Question
    {
        $question = new Question;
        $question->question_type = $type;
        $question->points = $points;

        $question->setRelation('options', new Collection(array_map(function (int $id, array $option) {
            $model = new QuestionOption;
            $model->id = $id;
            $model->option_text = $option[0];
            $model->is_correct = $option[1];

            return $model;
        }, array_keys($options), $options)));

        return $question;
    }

    public function test_multiple_choice_scores_the_correct_option_only(): void
    {
        $question = $this->question(QuestionType::MultipleChoice, [1 => ['3', false], 2 => ['4', true]]);
        $scorer = new AnswerScorer;

        $this->assertSame(['is_correct' => true, 'points' => 4.0], $scorer->score($question, [2], null));
        $this->assertSame(['is_correct' => false, 'points' => 0.0], $scorer->score($question, [1], null));
        $this->assertSame(['is_correct' => false, 'points' => 0.0], $scorer->score($question, [], null));
        $this->assertSame(['is_correct' => false, 'points' => 0.0], $scorer->score($question, null, null));
    }

    /**
     * @return array<string, array{0: list<int>, 1: bool}>
     */
    public static function multipleSelectAnswers(): array
    {
        return [
            'exactly the correct ones' => [[1, 2], true],
            'same ones in another order' => [[2, 1], true],
            'repeated ids' => [[1, 2, 2], true],
            'only some of them' => [[1], false],
            'with a wrong one' => [[1, 2, 3], false],
            'none' => [[], false],
        ];
    }

    /**
     * @param  list<int>  $chosen
     */
    #[DataProvider('multipleSelectAnswers')]
    public function test_multiple_select_needs_exactly_the_correct_options(array $chosen, bool $correct): void
    {
        $question = $this->question(QuestionType::MultipleSelect, [1 => ['A', true], 2 => ['B', true], 3 => ['C', false]]);

        $this->assertSame($correct, (new AnswerScorer)->score($question, $chosen, null)['is_correct']);
    }

    /**
     * @return array<string, array{0: string|null, 1: bool}>
     */
    public static function identificationAnswers(): array
    {
        return [
            'exact' => ['Jose Rizal', true],
            'other capitals' => ['JOSE rizal', true],
            'extra spaces' => ['  Jose    Rizal ', true],
            'another accepted answer' => ['Rizal', true],
            'wrong' => ['Bonifacio', false],
            'blank' => ['   ', false],
            'nothing' => [null, false],
        ];
    }

    #[DataProvider('identificationAnswers')]
    public function test_identification_ignores_capitals_and_spacing(?string $answer, bool $correct): void
    {
        $question = $this->question(QuestionType::Identification, [1 => ['Jose Rizal', true], 2 => ['Rizal', true]]);

        $this->assertSame($correct, (new AnswerScorer)->score($question, null, $answer)['is_correct']);
    }

    public function test_written_answers_are_left_for_the_teacher(): void
    {
        foreach ([QuestionType::ShortAnswer, QuestionType::Essay] as $type) {
            $this->assertSame(
                ['is_correct' => null, 'points' => null],
                (new AnswerScorer)->score($this->question($type, []), null, 'An answer'),
            );
        }
    }
}
