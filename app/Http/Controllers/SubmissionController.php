<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitWorkRequest;
use App\Models\ActivitySubmission;
use App\Models\LearningActivity;
use App\Services\SubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students' work on activities: handing it in, and grading it.
 */
class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissions) {}

    /**
     * Hand in work for an activity, or replace earlier work.
     */
    public function store(SubmitWorkRequest $request, LearningActivity $activity): RedirectResponse
    {
        $submission = $this->submissions->submit(
            $activity,
            $request->user()->student,
            $request->validated('body'),
            $request->file('attachments', []),
            $request->validated('remove_attachments', []),
            $request->user(),
        );

        $message = $submission->is_late
            ? 'Your work was handed in after the due date and is marked late.'
            : 'Your work was handed in.';

        return redirect()->route('activities.show', $activity)->with('success', $message);
    }

    /**
     * One student's work, with the grading form for teachers.
     */
    public function show(Request $request, ActivitySubmission $submission): Response
    {
        $this->authorize('view', $submission);

        $submission->load([
            'attachments',
            'student',
            'learningActivity.classSubject.subject',
            'learningActivity.classSubject.schoolClass',
            'learningActivity.classSubject.academicTerm',
            'grader',
        ]);

        return Inertia::render('Submissions/Show', [
            'submission' => [
                ...$submission->summary(),
                'student' => [
                    'id' => $submission->student->id,
                    'full_name' => $submission->student->full_name,
                    'student_number' => $submission->student->student_number,
                ],
                'grader' => $submission->grader?->name,
            ],
            'activity' => $submission->learningActivity->summary(),
            'can' => ['grade' => $request->user()->can('grade', $submission)],
        ]);
    }

    /**
     * Score the work and give feedback.
     */
    public function grade(Request $request, ActivitySubmission $submission): RedirectResponse
    {
        $this->authorize('grade', $submission);

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$submission->learningActivity->total_points],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->submissions->grade($submission, (float) $validated['score'], $validated['feedback'] ?? null, $request->user());

        $submission->load('student');

        return back()->with('success', "{$submission->student->full_name}'s work was graded.");
    }
}
