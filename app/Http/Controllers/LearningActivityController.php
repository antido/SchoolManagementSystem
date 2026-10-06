<?php

namespace App\Http\Controllers;

use App\Enums\ActiveStatus;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\QuestionType;
use App\Http\Requests\LearningActivityRequest;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attachment;
use App\Models\ClassSubject;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\Student;
use App\Models\User;
use App\Services\LearningActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assignments, quizzes, examinations, questionnaires and other activities.
 */
class LearningActivityController extends Controller
{
    private const RELATIONS = ['classSubject.subject', 'classSubject.schoolClass', 'classSubject.academicTerm'];

    public function __construct(private readonly LearningActivityService $activities) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LearningActivity::class);

        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search')),
            'type' => (string) $request->query('type'),
            'status' => (string) $request->query('status'),
        ];

        return Inertia::render('Activities/Index', [
            'activities' => LearningActivity::query()
                ->visibleTo($user)
                ->with(self::RELATIONS)
                ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
                ->when(ActivityType::tryFrom($filters['type']), fn ($query, $type) => $query->where('type', $type))
                ->when(ActivityStatus::tryFrom($filters['status']), fn ($query, $status) => $query->where('status', $status))
                ->orderByRaw('due_at is null')
                ->orderByDesc('due_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (LearningActivity $activity) => $activity->summary()),
            'filters' => $filters,
            'types' => ActivityType::options(ActivityType::allowedFor($user, 'view')),
            // Students only ever see published and closed activities.
            'statuses' => $user->student === null ? ActivityStatus::options() : [],
            // An existence check: loading every class subject just to decide on a button is costly for admins.
            'can' => ['create' => ActivityType::allowedFor($user, 'create') !== []
                && ClassSubject::query()->managedBy($user)->where('status', ActiveStatus::Active)->exists()],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $classSubjects = $this->creatableClassSubjects($user);

        abort_if(ActivityType::allowedFor($user, 'create') === [], 403);

        return Inertia::render('Activities/Create', [
            'classSubjects' => $classSubjects->map(fn (ClassSubject $classSubject) => [
                'value' => $classSubject->id,
                'label' => $classSubject->label(),
            ])->values(),
            'types' => ActivityType::options(ActivityType::allowedFor($user, 'create')),
            'defaults' => [
                'class_subject_id' => $classSubjects->firstWhere('id', (int) $request->query('class_subject'))?->id,
                'type' => ActivityType::tryFrom((string) $request->query('type'))?->value,
            ],
        ]);
    }

    public function store(LearningActivityRequest $request): RedirectResponse
    {
        $classSubject = ClassSubject::findOrFail($request->integer('class_subject_id'));

        $activity = $this->activities->create($classSubject, $request->user(), $request->validated());

        return redirect()->route('activities.show', $activity)
            ->with('success', "{$activity->title} was saved as a draft. Publish it when it is ready for students.");
    }

    public function show(Request $request, LearningActivity $activity): Response
    {
        $this->authorize('view', $activity);

        $user = $request->user();
        $activity->load([...self::RELATIONS, 'classSubject.teacher', 'teacher', 'attachments']);

        return Inertia::render('Activities/Show', [
            'activity' => [
                ...$activity->summary(),
                'description' => $activity->description,
                'instructions' => $activity->instructions,
                'teacher' => $activity->teacher ? ['id' => $activity->teacher->id, 'name' => $activity->teacher->full_name] : null,
                'created_at' => $activity->created_at->toIso8601String(),
                'attachments' => $activity->attachments->map(fn (Attachment $attachment) => $attachment->summary()),
            ],
            ...$this->submissionProps($user, $activity),
            ...$this->questionProps($user, $activity),
            ...$this->attemptProps($user, $activity),
            'can' => [
                'submit' => $user->can('submit', $activity),
                'attempt' => $user->can('attempt', $activity),
                'update' => $user->can('update', $activity),
                'delete' => $user->can('delete', $activity),
                'statuses' => array_values(array_map(
                    fn (ActivityStatus $status) => $status->value,
                    array_filter($activity->status->nextStatuses(), fn (ActivityStatus $status) => $user->can('changeStatus', [$activity, $status])),
                )),
            ],
        ]);
    }

    public function edit(Request $request, LearningActivity $activity): Response
    {
        $this->authorize('update', $activity);

        $activity->load([...self::RELATIONS, 'attachments']);

        return Inertia::render('Activities/Edit', [
            'activity' => [
                ...$activity->summary(),
                'description' => $activity->description,
                'instructions' => $activity->instructions,
                'attachments' => $activity->attachments->map(fn (Attachment $attachment) => $attachment->summary()),
            ],
            'types' => ActivityType::options(ActivityType::allowedFor($request->user(), 'update')),
        ]);
    }

    public function update(LearningActivityRequest $request, LearningActivity $activity): RedirectResponse
    {
        $this->activities->update($activity, $request->validated(), $request->user());

        return redirect()->route('activities.show', $activity)->with('success', 'The activity was updated.');
    }

    /**
     * Publish, close or reopen.
     */
    public function updateStatus(Request $request, LearningActivity $activity): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(ActivityStatus::class)]]);
        $status = ActivityStatus::from($validated['status']);

        $this->authorize('changeStatus', [$activity, $status]);

        try {
            $this->activities->changeStatus($activity, $status);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        $message = match ($status) {
            ActivityStatus::Published => "{$activity->title} is now visible to students.",
            ActivityStatus::Closed => "{$activity->title} was closed.",
            ActivityStatus::Draft => "{$activity->title} was moved back to draft.",
        };

        return back()->with('success', $message);
    }

    public function destroy(Request $request, LearningActivity $activity): RedirectResponse
    {
        if ($activity->hasDependents() && $request->user()->can('update', $activity)) {
            return back()->with('error', 'Students have already worked on this activity. Close it instead.');
        }

        $this->authorize('delete', $activity);

        $this->activities->delete($activity);

        return redirect()->route('activities.index')->with('success', "{$activity->title} was deleted.");
    }

    /**
     * Attempts at a quiz, examination or questionnaire:
     * - for a student, their own attempts and whether they can start one;
     * - for whoever may see submissions, every student in the class with
     *   their best score.
     *
     * @return array<string, mixed>
     */
    private function attemptProps(User $user, LearningActivity $activity): array
    {
        if (! $activity->type->usesQuestions()) {
            return [];
        }

        if ($user->student !== null) {
            $attempts = $activity->attempts()->where('student_id', $user->student->id)->orderBy('attempt_number')->get();

            return [
                'myAttempts' => $attempts->map(fn (ActivityAttempt $attempt) => $attempt->summary())->values(),
                'attemptInProgress' => $attempts->first(fn (ActivityAttempt $attempt) => $attempt->isInProgress())?->id,
                'questionCount' => $activity->questions()->count(),
            ];
        }

        if (! $user->can('submissions.view') || ! $activity->classSubject->isManagedBy($user)) {
            return [];
        }

        $attempts = $activity->attempts()->orderBy('attempt_number')->get()->groupBy('student_id');

        $students = Student::query()
            ->where(fn ($query) => $query
                ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $activity->classSubject->school_class_id))
                ->orWhereIn('id', $attempts->keys()))
            ->orderByName()
            ->get();

        return [
            'attemptRoster' => $students->map(function (Student $student) use ($attempts, $activity) {
                $mine = $attempts->get($student->id, collect());
                $latest = $mine->last();
                $best = $mine->whereNotNull('score')->sortByDesc('score')->first();

                return [
                    'student' => ['id' => $student->id, 'name' => $student->sortable_name, 'student_number' => $student->student_number],
                    'attempts' => $mine->count(),
                    'latest_attempt_id' => $latest?->id,
                    'status' => $latest?->status->value ?? ($activity->isPastDue() ? 'missing' : 'pending'),
                    'best_score' => $best?->score === null ? null : (float) $best->score,
                ];
            })->values(),
        ];
    }

    /**
     * The questions with their answers, for whoever manages the activity,
     * and the other activities they could copy questions from.
     *
     * @return array<string, mixed>
     */
    private function questionProps(User $user, LearningActivity $activity): array
    {
        if (! $user->can('viewAny', [Question::class, $activity])) {
            return [];
        }

        $canChange = $user->can('create', [Question::class, $activity]);

        return [
            'questions' => $activity->questions()->with('options')->get()->map(fn (Question $question) => $question->summary(withAnswers: true)),
            'questionTypes' => QuestionType::options(),
            'copySources' => $canChange
                ? LearningActivity::query()
                    ->whereKeyNot($activity->id)
                    ->whereIn('type', array_filter(ActivityType::cases(), fn (ActivityType $type) => $type->usesQuestions()))
                    ->whereHas('classSubject', fn ($query) => $query->managedBy($user))
                    ->whereHas('questions')
                    ->with('classSubject.schoolClass')
                    ->withCount('questions')
                    ->latest('id')
                    ->limit(50)
                    ->get()
                    ->map(fn (LearningActivity $source) => [
                        'value' => $source->id,
                        'label' => "{$source->title} ({$source->classSubject->schoolClass->name}, {$source->questions_count} questions)",
                    ])
                : [],
            'questionsLocked' => $activity->questionsAreLocked(),
            'canChangeQuestions' => $canChange,
        ];
    }

    /**
     * Work handed in for the activity:
     * - for a student, their own work and its status;
     * - for whoever may see submissions, every student in the class with
     *   the status of their work.
     *
     * @return array<string, mixed>
     */
    private function submissionProps(User $user, LearningActivity $activity): array
    {
        if (! $activity->type->acceptsSubmissions()) {
            return [];
        }

        if ($user->student !== null) {
            $submission = $activity->submissions()->where('student_id', $user->student->id)->with('attachments')->first();
            $submission?->setRelation('learningActivity', $activity);

            return [
                'mySubmission' => $submission?->summary(),
                'myStatus' => ActivitySubmission::displayStatus($submission, $activity),
            ];
        }

        if (! $user->can('submissions.view') || ! $activity->classSubject->isManagedBy($user)) {
            return [];
        }

        $submissions = $activity->submissions()->get()->keyBy('student_id');

        // Everyone enrolled now, plus anyone who handed in work and has since left.
        $students = Student::query()
            ->where(fn ($query) => $query
                ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $activity->classSubject->school_class_id))
                ->orWhereIn('id', $submissions->keys()))
            ->orderByName()
            ->get();

        return [
            'roster' => $students->map(function (Student $student) use ($submissions, $activity) {
                /** @var ActivitySubmission|null $submission */
                $submission = $submissions->get($student->id);

                return [
                    'student' => ['id' => $student->id, 'name' => $student->sortable_name, 'student_number' => $student->student_number],
                    'submission_id' => $submission?->id,
                    'status' => ActivitySubmission::displayStatus($submission, $activity),
                    'submitted_at' => $submission?->submitted_at->toIso8601String(),
                    // Kept separately: once graded, the status no longer says the work was late.
                    'is_late' => (bool) $submission?->is_late,
                    'score' => $submission?->score === null ? null : (float) $submission->score,
                ];
            })->values(),
        ];
    }

    /**
     * Active class subjects this person may add activities to, when they may
     * create at least one kind.
     *
     * @return Collection<int, ClassSubject>
     */
    private function creatableClassSubjects(User $user): Collection
    {
        if (ActivityType::allowedFor($user, 'create') === []) {
            return collect();
        }

        return ClassSubject::query()
            ->managedBy($user)
            ->where('status', ActiveStatus::Active)
            ->with(['subject', 'schoolClass', 'academicTerm'])
            ->get()
            ->sortBy(fn (ClassSubject $classSubject) => $classSubject->label())
            ->values();
    }
}
