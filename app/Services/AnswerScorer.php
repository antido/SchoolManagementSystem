<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;

/**
 * Scores answers to objective questions. Short answers and essays are left
 * for the teacher (null).
 *
 * - Multiple choice and true/false: full points when the chosen option is correct.
 * - Multiple select: full points only when exactly the correct options are
 *   chosen; there is no partial credit.
 * - Identification: full points when the text matches an accepted answer,
 *   ignoring capitals and extra spaces.
 */
class AnswerScorer
{
    /**
     * @param  list<int>|null  $selectedOptionIds
     * @return array{is_correct: bool|null, points: float|null}
     */
    public function score(Question $question, ?array $selectedOptionIds, ?string $answerText): array
    {
        $correct = match ($question->question_type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse, QuestionType::MultipleSelect => $this->choicesAreCorrect($question, $selectedOptionIds ?? []),
            QuestionType::Identification => $this->textIsAccepted($question, $answerText),
            QuestionType::ShortAnswer, QuestionType::Essay => null,
        };

        return [
            'is_correct' => $correct,
            'points' => $correct === null ? null : ($correct ? (float) $question->points : 0.0),
        ];
    }

    /**
     * @param  list<int>  $selected
     */
    private function choicesAreCorrect(Question $question, array $selected): bool
    {
        $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $chosen = collect($selected)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        return $chosen !== [] && $chosen === $correct;
    }

    private function textIsAccepted(Question $question, ?string $answer): bool
    {
        $given = self::normalise((string) $answer);

        return $given !== '' && $question->options->contains(
            fn (QuestionOption $option) => self::normalise($option->option_text) === $given,
        );
    }

    /**
     * "  Jose   RIZAL " -> "jose rizal"
     */
    public static function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
    }
}
