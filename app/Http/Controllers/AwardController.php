<?php

namespace App\Http\Controllers;

use App\Enums\ActiveStatus;
use App\Http\Requests\AwardRequest;
use App\Http\Requests\GrantAwardRequest;
use App\Models\AcademicYear;
use App\Models\Award;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentAward;
use App\Services\AwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school's awards, and giving them to students.
 */
class AwardController extends Controller
{
    private const GRANT_RELATIONS = ['award.attachments', 'academicYear', 'schoolClass', 'awardedBy'];

    public function __construct(private readonly AwardService $awards) {}

    /**
     * The list of awards. Students also see the awards they received.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Award::class);

        $user = $request->user();
        $staff = $user->student === null;

        return Inertia::render('Awards/Index', [
            'awards' => Award::query()
                ->when(! $staff, fn ($query) => $query->where('status', ActiveStatus::Active))
                ->with('attachments')
                ->withCount(['studentAwards as given_count' => fn ($query) => $query->notRevoked()])
                ->orderBy('name')
                ->get()
                ->map(fn (Award $award) => [...$award->summary(), 'given_count' => $award->given_count]),
            'myAwards' => $user->student
                ? $user->student->awards()->notRevoked()->with(self::GRANT_RELATIONS)->latest('awarded_on')->get()
                    ->map(fn (StudentAward $studentAward) => $studentAward->summary())
                : null,
            'can' => ['create' => $user->can('create', Award::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Award::class);

        return Inertia::render('Awards/Create', ['statuses' => ActiveStatus::options()]);
    }

    public function store(AwardRequest $request): RedirectResponse
    {
        $award = $this->awards->save(null, $request->validated(), $request->file('image'), false, $request->user());

        return redirect()->route('awards.show', $award)->with('success', "{$award->name} was added.");
    }

    /**
     * An award, everyone who received it, and the form to give it.
     */
    public function show(Request $request, Award $award): Response
    {
        $this->authorize('view', $award);

        $user = $request->user();
        $award->load('attachments');

        // Staff see every recipient; students only themselves.
        $recipients = $award->studentAwards()
            ->with([...self::GRANT_RELATIONS, 'student'])
            ->when($user->student !== null, fn ($query) => $query->where('student_id', $user->student->id)->notRevoked())
            ->when($user->student === null && $user->isLimitedToOwnClasses(), fn ($query) => $query->whereIn(
                'student_id',
                Student::query()->visibleTo($user)->select('id'),
            ))
            ->latest('awarded_on')
            ->latest('id')
            ->get();

        return Inertia::render('Awards/Show', [
            'award' => $award->summary(),
            'recipients' => $recipients->map(fn (StudentAward $studentAward) => [
                ...$studentAward->summary(),
                'student' => ['id' => $studentAward->student->id, 'full_name' => $studentAward->student->full_name],
                'can_revoke' => $user->can('revoke', [Award::class, $studentAward]),
            ]),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get()
                ->map(fn (AcademicYear $year) => ['value' => $year->id, 'label' => $year->name]),
            'activeYearId' => AcademicYear::active()?->id,
            // Loaded when the grant dialog searches for a student.
            'candidates' => Inertia::optional(fn () => $this->candidates($request)),
            'can' => [
                'update' => $user->can('update', $award),
                'delete' => $user->can('delete', $award),
                'grant' => $user->can('grant', $award),
            ],
        ]);
    }

    public function edit(Award $award): Response
    {
        $this->authorize('update', $award);

        return Inertia::render('Awards/Edit', [
            'award' => $award->load('attachments')->summary(),
            'statuses' => ActiveStatus::options(),
        ]);
    }

    public function update(AwardRequest $request, Award $award): RedirectResponse
    {
        $this->awards->save($award, $request->validated(), $request->file('image'), $request->boolean('remove_image'), $request->user());

        return redirect()->route('awards.show', $award)->with('success', "{$award->name} was updated.");
    }

    public function destroy(Request $request, Award $award): RedirectResponse
    {
        if ($award->hasDependents() && $request->user()->can('awards.delete')) {
            return back()->with('error', 'This award has been given to students. Make it inactive instead.');
        }

        $this->authorize('delete', $award);

        $this->awards->delete($award);

        return redirect()->route('awards.index')->with('success', "{$award->name} was deleted.");
    }

    public function grant(GrantAwardRequest $request, Award $award): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));

        $this->awards->grant($award, $student, $request->validated(), $request->user());

        return back()->with('success', "{$award->name} was given to {$student->full_name}.");
    }

    public function revoke(Request $request, StudentAward $studentAward): RedirectResponse
    {
        $this->authorize('revoke', [Award::class, $studentAward]);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->awards->revoke($studentAward, $validated['reason'], $request->user());

        return back()->with('success', 'The award was revoked. It stays in the history, marked revoked.');
    }

    /**
     * Students matching the search that this person may give awards to,
     * with the classes each has been in.
     *
     * @return list<array<string, mixed>>
     */
    private function candidates(Request $request): array
    {
        $search = trim((string) $request->query('candidate_search'));

        if (mb_strlen($search) < 2) {
            return [];
        }

        return Student::query()
            ->visibleTo($request->user())
            ->search($search)
            ->with(['enrollments.schoolClass'])
            ->orderByName()
            ->limit(15)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->sortable_name,
                'student_number' => $student->student_number,
                'classes' => $student->enrollments
                    ->sortByDesc('enrolled_at')
                    ->map(fn (Enrollment $enrollment) => [
                        'value' => $enrollment->school_class_id,
                        'label' => $enrollment->schoolClass->name,
                        'academic_year_id' => $enrollment->academic_year_id,
                    ])
                    ->unique('value')
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
