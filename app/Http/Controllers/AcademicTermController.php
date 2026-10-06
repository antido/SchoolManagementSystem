<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicTermRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Services\AcademicPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Terms are managed on their academic year's page, so every action here
 * sends the user back to that page.
 */
class AcademicTermController extends Controller
{
    public function __construct(private AcademicPeriodService $periods) {}

    public function store(AcademicTermRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $term = new AcademicTerm($request->validated());
        $term->academicYear()->associate($academicYear);
        $term->save();

        return back()->with('success', "{$term->name} was added to {$academicYear->name}.");
    }

    public function update(AcademicTermRequest $request, AcademicTerm $academicTerm): RedirectResponse
    {
        $academicTerm->update($request->validated());

        return back()->with('success', "{$academicTerm->name} was updated.");
    }

    /**
     * Make this the active term (its year becomes active too).
     */
    public function activate(AcademicTerm $academicTerm): RedirectResponse
    {
        $this->authorize('update', $academicTerm);

        $this->periods->activateTerm($academicTerm);

        return back()->with('success', "{$academicTerm->name} is now the active term.");
    }

    public function destroy(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        if ($academicTerm->hasDependents() && $request->user()->can('academic_terms.delete')) {
            return back()->with('error', "{$academicTerm->name} has academic records, so it is kept for history and cannot be deleted.");
        }

        $this->authorize('delete', $academicTerm);

        $academicTerm->delete();

        return back()->with('success', "{$academicTerm->name} was deleted.");
    }
}
