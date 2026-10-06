<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Events\AttemptGraded;
use App\Models\ActivityAttempt;
use App\Models\AttemptAnswer;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Students taking quizzes, examinations and questionnaires.
 *
 * Time is kept by the server: each attempt gets an expiry when it starts
 * (the time limit, cut short by the due date), answers are refused after
 * it, and attempts left open are handed in automatically, either the next
 * time anyone touches them or by the scheduled `attempts:close-expired`.
 */
class AttemptService
{
    public function __construct(private readonly AnswerScorer $scorer) {}

    /**
     * Start a new attempt, or return the one already in progress.
     *
     * @throws ValidationException when no attempts are left
     */
    public function start(LearningActivity $activity, Student $student): ActivityAttempt
    {
        return DB::transaction(function () use ($activity, $student) {
            $attempts = ActivityAttempt::query()
                ->where('learning_activity_id', $activity->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->get();

            $open = $attempts->first(fn (ActivityAttempt $attempt) => $attempt->isInProgress());

            if ($open !== null) {
                if (! $open->hasExpired()) {
                    return $open;
                }

                $this->submit($open, auto: true);
            }

            if ($attempts->count() >= $activity->max_attempts) {
                throw ValidationException::withMessages(['attempt' => 'You have used all your attempts.']);
            }

            $questions = $activity->questions()->with('options')->get();
            $questionIds = $questions->pluck('id')->all();

            if ($activity->shuffle_questions) {
                shuffle($questionIds);
            }

            $optionOrder = $questions
                ->filter(fn (Question $question) => $question->question_type->hasChoices())
                ->mapWithKeys(function (Question $question) use ($activity) {
                    $ids = $question->options->pluck('id')->all();

                    // True/false always reads True, False.
                    if ($activity->shuffle_options && $question->question_type !== QuestionType::TrueFalse) {
                        shuffle($ids);
                    }

                    return [$question->id => $ids];
                })
                ->all();

            $attempt = new ActivityAttempt;
            $attempt->learning_activity_id = $activity->id;
            $attempt->student_id = $student->id;
            $attempt->attempt_number = $attempts->count() + 1;
            $attempt->status = AttemptStatus::InProgress;
            $attempt->started_at = now();
            $attempt->expires_at = $this->expiryFor($activity);
            $attempt->question_order = $questionIds;
            $attempt->option_order = $optionOrder;
            $attempt->save();

            return $attempt;
        });
    }

    /**
     * Save (or replace) the answer to one question while the attempt is open.
     *
     * @param  list<int>|null  $selectedOptionIds
     *
     * @throws ValidationException when the attempt is over or the answer does not fit the question
     */
    public function saveAnswer(ActivityAttempt $attempt, int $questionId, ?array $selectedOptionIds, ?string $answerText): AttemptAnswer
    {
        $this->closeIfExpired($attempt);

        if (! $attempt->isInProgress()) {
            throw ValidationException::withMessages(['attempt' => 'This attempt is over. Your saved answers were handed in.']);
        }

        if (! in_array($questionId, $attempt->question_order, true)) {
            throw ValidationException::withMessages(['question_id' => 'This question is not part of the attempt.']);
        }

        $question = Question::with('options')->findOrFail($questionId);
        $selected = null;
        $text = null;

        if ($question->question_type->hasChoices()) {
            $selected = array_values(array_unique(array_map('intval', $selectedOptionIds ?? [])));

            if (array_diff($selected, $question->options->pluck('id')->all()) !== []) {
                throw ValidationException::withMessages(['selected_options' => 'Choose from the options given.']);
            }

            if ($question->question_type !== QuestionType::MultipleSelect && count($selected) > 1) {
                throw ValidationException::withMessages(['selected_options' => 'Choose one option.']);
            }
        } else {
            $text = $answerText;
        }

        return AttemptAnswer::query()->updateOrCreate(
            ['activity_attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['selected_options' => $selected, 'answer_text' => $text],
        );
    }

    /**
     * Hand in the attempt: score every objective answer and record the
     * total. Unanswered questions score zero. If no answer needs the
     * teacher, the attempt is graded straight away.
     */
    public function submit(ActivityAttempt $attempt, bool $auto = false): ActivityAttempt
    {
        return DB::transaction(function () use ($attempt, $auto) {
            $attempt = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $attempt->isInProgress()) {
                return $attempt;
            }

            $questions = Question::query()->with('options')->whereKey($attempt->question_order)->get()->keyBy('id');
            $answers = $attempt->answers()->get()->keyBy('question_id');

            foreach ($attempt->question_order as $questionId) {
                $question = $questions->get($questionId);

                if ($question === null) {
                    continue;
                }

                $answer = $answers->get($questionId) ?? new AttemptAnswer([]);
                $answer->activity_attempt_id = $attempt->id;
                $answer->question_id = $questionId;

                $result = $this->scorer->score($question, $answer->selected_options, $answer->answer_text);

                // A blank written answer gets zero without waiting for the teacher.
                if ($result['points'] === null && blank($answer->answer_text)) {
                    $result = ['is_correct' => false, 'points' => 0.0];
                }

                $answer->is_correct = $result['is_correct'];
                $answer->points_awarded = $result['points'];
                $answer->save();
            }

            $attempt->submitted_at = $auto && $attempt->expires_at !== null ? min(now(), $attempt->expires_at) : now();
            $attempt->auto_submitted = $auto;
            $this->refreshScore($attempt);

            return $attempt;
        });
    }

    /**
     * Teacher scoring of written answers.
     *
     * @param  array<int|string, array{points: float|int|string, feedback?: string|null}>  $scores  keyed by answer id
     *
     * @throws ValidationException when a score is more than the question is worth
     */
    public function grade(ActivityAttempt $attempt, array $scores): ActivityAttempt
    {
        return DB::transaction(function () use ($attempt, $scores) {
            $answers = $attempt->answers()->with('question')->whereKey(array_keys($scores))->get();

            foreach ($answers as $answer) {
                $points = (float) $scores[$answer->id]['points'];

                if ($points > (float) $answer->question->points) {
                    throw ValidationException::withMessages([
                        "scores.{$answer->id}.points" => "This question is worth {$answer->question->points} points.",
                    ]);
                }

                $answer->points_awarded = $points;
                $answer->is_correct = $points >= (float) $answer->question->points;
                $answer->feedback = $scores[$answer->id]['feedback'] ?? null;
                $answer->save();
            }

            $this->refreshScore($attempt);

            return $attempt;
        });
    }

    /**
     * Hand in an open attempt whose time is up.
     */
    public function closeIfExpired(ActivityAttempt $attempt): void
    {
        if ($attempt->isInProgress() && $attempt->hasExpired()) {
            $this->submit($attempt, auto: true);
            $attempt->refresh();
        }
    }

    /**
     * Hand in every attempt whose time is up. Returns how many were closed.
     */
    public function closeExpired(): int
    {
        $count = 0;

        ActivityAttempt::query()->expired()->each(function (ActivityAttempt $attempt) use (&$count): void {
            $this->submit($attempt, auto: true);
            $count++;
        });

        return $count;
    }

    /**
     * Hand in every open attempt, for when the teacher closes the activity.
     */
    public function closeAllFor(LearningActivity $activity): void
    {
        $activity->attempts()->inProgress()->each(fn (ActivityAttempt $attempt) => $this->submit($attempt, auto: true));
    }

    /**
     * The total of the scored answers; graded once nothing is waiting for the teacher.
     */
    private function refreshScore(ActivityAttempt $attempt): void
    {
        $answers = $attempt->answers()->get();

        $wasGraded = $attempt->getOriginal('status') === AttemptStatus::Graded;

        $attempt->score = $answers->sum(fn (AttemptAnswer $answer) => (float) $answer->points_awarded);
        $attempt->status = $answers->contains(fn (AttemptAnswer $answer) => $answer->points_awarded === null)
            ? AttemptStatus::Submitted
            : AttemptStatus::Graded;
        $attempt->save();

        // The student hears once, when the last answer is scored.
        if (! $wasGraded && $attempt->status === AttemptStatus::Graded) {
            AttemptGraded::dispatch($attempt);
        }
    }

    /**
     * When an attempt started now must end: after the time limit, and no
     * later than the due date. Null when neither applies.
     */
    private function expiryFor(LearningActivity $activity): ?CarbonInterface
    {
        $byLimit = $activity->duration_minutes ? now()->addMinutes($activity->duration_minutes) : null;
        $byDueDate = $activity->due_at;

        return match (true) {
            $byLimit === null => $byDueDate,
            $byDueDate === null => $byLimit,
            default => $byLimit->min($byDueDate),
        };
    }
}
