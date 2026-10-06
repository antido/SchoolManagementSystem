<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Models\ActivityAttempt;
use App\Models\AttemptAnswer;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taking quizzes, examinations and questionnaires, and scoring the answers.
 */
class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attempts) {}

    /**
     * Start an attempt, or go back to the one in progress.
     */
    public function store(Request $request, LearningActivity $activity): RedirectResponse
    {
        $this->authorize('attempt', $activity);

        try {
            $attempt = $this->attempts->start($activity, $request->user()->student);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('attempts.show', $attempt);
    }

    /**
     * The exam screen while the attempt is open; the results afterwards.
     */
    public function show(Request $request, ActivityAttempt $attempt): Response
    {
        $this->authorize('view', $attempt);

        $this->attempts->closeIfExpired($attempt);

        $attempt->load([
            'student',
            'learningActivity.classSubject.subject',
            'learningActivity.classSubject.schoolClass',
            'learningActivity.classSubject.academicTerm',
        ]);

        $user = $request->user();
        $activity = $attempt->learningActivity;
        $isOwner = $user->student?->id === $attempt->student_id;

        if ($attempt->isInProgress() && $isOwner) {
            return $this->takePage($attempt);
        }

        // Students see the answer key once the teacher closes the activity.
        $showAnswers = ! $isOwner || $activity->status === ActivityStatus::Closed;
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return Inertia::render('Attempts/Show', [
            'attempt' => [
                ...$attempt->summary(),
                'student' => ['id' => $attempt->student->id, 'full_name' => $attempt->student->full_name],
            ],
            'activity' => $activity->summary(),
            'items' => $this->orderedQuestions($attempt)->map(function (Question $question) use ($answers, $showAnswers) {
                /** @var AttemptAnswer|null $answer */
                $answer = $answers->get($question->id);

                return [
                    'question' => $question->summary(withAnswers: $showAnswers),
                    'answer' => $answer ? [
                        'id' => $answer->id,
                        'selected_options' => $answer->selected_options ?? [],
                        'answer_text' => $answer->answer_text,
                        // While the activity is open, per-question results would reveal the
                        // key to a student with attempts left, so only the total is shown.
                        'is_correct' => $showAnswers ? $answer->is_correct : null,
                        'points_awarded' => ! $showAnswers || $answer->points_awarded === null ? null : (float) $answer->points_awarded,
                        'feedback' => $answer->feedback,
                    ] : null,
                    'needs_teacher' => ! $question->question_type->isAutoScored(),
                ];
            })->values(),
            'showAnswers' => $showAnswers,
            'can' => ['grade' => $user->can('grade', $attempt)],
        ]);
    }

    /**
     * Save one answer. Called by the exam screen as the student works, so
     * it answers with JSON instead of a page.
     */
    public function saveAnswer(Request $request, ActivityAttempt $attempt): JsonResponse
    {
        $this->authorize('answer', $attempt);

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_options' => ['nullable', 'array', 'max:10'],
            'selected_options.*' => ['integer'],
            'answer_text' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->attempts->saveAnswer(
            $attempt,
            (int) $validated['question_id'],
            $validated['selected_options'] ?? null,
            $validated['answer_text'] ?? null,
        );

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'seconds_remaining' => $attempt->secondsRemaining(),
        ]);
    }

    /**
     * Hand in the attempt.
     */
    public function submit(Request $request, ActivityAttempt $attempt): RedirectResponse
    {
        $this->authorize('view', $attempt);
        abort_unless($request->user()->student?->id === $attempt->student_id, 403);

        if (! $attempt->isInProgress()) {
            return redirect()->route('attempts.show', $attempt)->with('success', 'This attempt was already handed in.');
        }

        $this->attempts->submit($attempt, auto: $attempt->hasExpired());

        return redirect()->route('attempts.show', $attempt)->with('success', 'Your answers were handed in.');
    }

    /**
     * Score written answers.
     */
    public function grade(Request $request, ActivityAttempt $attempt): RedirectResponse
    {
        $this->authorize('grade', $attempt);

        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.points' => ['required', 'numeric', 'min:0'],
            'scores.*.feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->attempts->grade($attempt, $validated['scores']);

        return back()->with('success', 'The scores were saved.');
    }

    private function takePage(ActivityAttempt $attempt): Response
    {
        $activity = $attempt->learningActivity;
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return Inertia::render('Attempts/Take', [
            'attempt' => [
                ...$attempt->summary(),
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'seconds_remaining' => $attempt->secondsRemaining(),
            ],
            'activity' => [
                ...$activity->summary(),
                'instructions' => $activity->instructions,
            ],
            'questions' => $this->orderedQuestions($attempt)->map(fn (Question $question) => [
                ...$question->summary(),
                // Options in the order this student was given.
                'options' => $question->question_type->hasChoices()
                    ? collect($attempt->option_order[$question->id] ?? $question->options->pluck('id')->all())
                        ->map(fn (int $id) => $question->options->firstWhere('id', $id))
                        ->filter()
                        ->map(fn (QuestionOption $option) => ['id' => $option->id, 'text' => $option->option_text])
                        ->values()
                    : [],
            ])->values(),
            'answers' => $answers->map(fn (AttemptAnswer $answer) => [
                'selected_options' => $answer->selected_options ?? [],
                'answer_text' => $answer->answer_text ?? '',
            ]),
        ]);
    }

    /**
     * The attempt's questions, in the order the student was given them.
     *
     * @return Collection<int, Question>
     */
    private function orderedQuestions(ActivityAttempt $attempt): Collection
    {
        $questions = Question::query()->with('options')->whereKey($attempt->question_order)->get()->keyBy('id');

        return collect($attempt->question_order)->map(fn (int $id) => $questions->get($id))->filter()->values();
    }
}
