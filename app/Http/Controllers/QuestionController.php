<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionRequest;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Services\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The questions of a quiz, examination or questionnaire. They are managed
 * on the activity's page.
 */
class QuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questions) {}

    public function store(QuestionRequest $request, LearningActivity $activity): RedirectResponse
    {
        $this->questions->create($activity, $request->validated());

        return back()->with('success', 'The question was added.');
    }

    public function update(QuestionRequest $request, Question $question): RedirectResponse
    {
        $this->questions->update($question, $request->validated());

        return back()->with('success', 'The question was updated.');
    }

    /**
     * Move a question one place up or down.
     */
    public function move(Request $request, Question $question): RedirectResponse
    {
        $this->authorize('update', $question);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $this->questions->move($question, $validated['direction']);

        return back();
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        $this->questions->delete($question);

        return back()->with('success', 'The question was removed.');
    }

    /**
     * Copy all questions from another activity the person manages.
     */
    public function copy(Request $request, LearningActivity $activity): RedirectResponse
    {
        $this->authorize('create', [Question::class, $activity]);

        $validated = $request->validate([
            'source_activity_id' => ['required', 'integer', Rule::notIn([$activity->id]), 'exists:learning_activities,id'],
        ]);

        $source = LearningActivity::findOrFail($validated['source_activity_id']);

        $this->authorize('viewAny', [Question::class, $source]);

        $count = $this->questions->copy($source, $activity);

        return back()->with('success', $count === 1 ? '1 question was copied.' : "{$count} questions were copied.");
    }
}
