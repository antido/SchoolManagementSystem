<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\LearningActivity;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Builds the questions of a quiz, examination or questionnaire. After every
 * change the activity's total points are set to the sum of its questions.
 */
class QuestionService
{
    /**
     * Add a question at the end.
     *
     * @param  array<string, mixed>  $data  validated QuestionRequest data
     */
    public function create(LearningActivity $activity, array $data): Question
    {
        return DB::transaction(function () use ($activity, $data) {
            $question = new Question;
            $question->learning_activity_id = $activity->id;
            $question->order = (int) $activity->questions()->max('order') + 1;
            $this->fill($question, $data);

            $this->syncTotalPoints($activity);

            return $question;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated QuestionRequest data
     */
    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            $this->fill($question, $data);
            $this->syncTotalPoints($question->learningActivity);

            return $question;
        });
    }

    public function delete(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            $activity = $question->learningActivity;
            $question->delete();
            $this->renumber($activity);
            $this->syncTotalPoints($activity);
        });
    }

    /**
     * Swap the question with the one before ("up") or after ("down") it.
     */
    public function move(Question $question, string $direction): void
    {
        DB::transaction(function () use ($question, $direction): void {
            $questions = $question->learningActivity->questions()->lockForUpdate()->get()->values();
            $index = $questions->search(fn (Question $other) => $other->is($question));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $questions->has($target)) {
                return;
            }

            [$questions[$index], $questions[$target]] = [$questions[$target], $questions[$index]];

            $questions->each(fn (Question $other, int $position) => $other->forceFill(['order' => $position + 1])->save());
        });
    }

    /**
     * Copy every question (with options) from another activity onto the end
     * of this one. Returns how many were copied.
     */
    public function copy(LearningActivity $from, LearningActivity $to): int
    {
        return DB::transaction(function () use ($from, $to) {
            $order = (int) $to->questions()->max('order');
            $sources = $from->questions()->with('options')->get();

            foreach ($sources as $source) {
                $copy = $source->replicate(['learning_activity_id', 'order']);
                $copy->learning_activity_id = $to->id;
                $copy->order = ++$order;
                $copy->save();

                foreach ($source->options as $option) {
                    $copy->options()->save($option->replicate(['question_id']));
                }
            }

            $this->syncTotalPoints($to);

            return $sources->count();
        });
    }

    /**
     * The activity's total points become the sum of its questions' points.
     */
    public function syncTotalPoints(LearningActivity $activity): void
    {
        $activity->forceFill(['total_points' => $activity->questions()->sum('points')])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Question $question, array $data): void
    {
        $type = QuestionType::from($data['question_type']);

        $question->question = $data['question'];
        $question->question_type = $type;
        $question->points = $data['points'];
        $question->save();

        // Options are replaced as a whole. Questions only change before any
        // student has answered them, so no answer points at an old option.
        $question->options()->delete();

        foreach ($this->optionsFor($type, $data) as $order => [$text, $correct]) {
            $question->options()->forceCreate(['option_text' => $text, 'is_correct' => $correct, 'order' => $order + 1]);
        }

        $question->unsetRelation('options');
    }

    /**
     * The options to store for the type, as [text, is correct] pairs.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{0: string, 1: bool}>
     */
    private function optionsFor(QuestionType $type, array $data): array
    {
        $given = array_map(
            fn (array $option) => [trim($option['text']), (bool) ($option['is_correct'] ?? false)],
            array_values($data['options'] ?? []),
        );

        return match ($type) {
            QuestionType::TrueFalse => [
                ['True', $data['correct_answer'] === 'true'],
                ['False', $data['correct_answer'] === 'false'],
            ],
            // Every listed answer is accepted.
            QuestionType::Identification => array_map(fn (array $option) => [$option[0], true], $given),
            QuestionType::MultipleChoice, QuestionType::MultipleSelect => $given,
            QuestionType::ShortAnswer, QuestionType::Essay => [],
        };
    }

    /**
     * Number the questions 1, 2, 3... again after one is removed.
     */
    private function renumber(LearningActivity $activity): void
    {
        $activity->questions()->get()->values()
            ->each(fn (Question $question, int $position) => $question->forceFill(['order' => $position + 1])->save());
    }
}
