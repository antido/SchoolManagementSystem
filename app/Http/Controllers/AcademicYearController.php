<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYearRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Services\AcademicPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private AcademicPeriodService $periods) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AcademicYear::class);

        return Inertia::render('AcademicYears/Index', [
            'years' => AcademicYear::query()
                ->withCount('terms')
                ->latestFirst()
                ->paginate(15)
                ->through(fn (AcademicYear $year) => [...$year->summary(), 'terms_count' => $year->terms_count]),
            'can' => ['create' => $request->user()->can('create', AcademicYear::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AcademicYear::class);

        return Inertia::render('AcademicYears/Create');
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $year = AcademicYear::create($request->validated());

        return redirect()
            ->route('academic-years.show', $year)
            ->with('success', "Academic year {$year->name} was created. Add its terms below.");
    }

    public function show(Request $request, AcademicYear $academicYear): Response
    {
        $this->authorize('view', $academicYear);

        $user = $request->user();

        return Inertia::render('AcademicYears/Show', [
            'year' => $academicYear->summary(),
            'terms' => $academicYear->terms->map(fn (AcademicTerm $term) => [
                ...$term->summary(),
                'can_delete' => $user->can('delete', $term),
            ]),
            'can' => [
                'update' => $user->can('update', $academicYear),
                'delete' => $user->can('delete', $academicYear),
                'createTerm' => $user->can('create', AcademicTerm::class),
                'updateTerms' => $user->can('academic_terms.update'),
            ],
        ]);
    }

    public function edit(AcademicYear $academicYear): Response
    {
        $this->authorize('update', $academicYear);

        return Inertia::render('AcademicYears/Edit', ['year' => $academicYear->summary()]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());

        return redirect()
            ->route('academic-years.show', $academicYear)
            ->with('success', "Academic year {$academicYear->name} was updated.");
    }

    /**
     * Make this the active year, or stop it being active.
     */
    public function activate(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $validated = $request->validate(['active' => ['required', 'boolean']]);

        if ($validated['active']) {
            $this->periods->activateYear($academicYear);

            return back()->with('success', "{$academicYear->name} is now the active academic year.");
        }

        $this->periods->deactivateYear($academicYear);

        return back()->with('success', "{$academicYear->name} is no longer active.");
    }

    public function destroy(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->hasDependents() && $request->user()->can('academic_years.delete')) {
            return back()->with('error', "{$academicYear->name} has terms or academic records, so it is kept for history and cannot be deleted.");
        }

        $this->authorize('delete', $academicYear);

        $academicYear->delete();

        return redirect()
            ->route('academic-years.index')
            ->with('success', "Academic year {$academicYear->name} was deleted.");
    }
}
