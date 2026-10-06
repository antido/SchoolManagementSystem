<?php

namespace App\Http\Controllers;

use App\Enums\ActiveStatus;
use App\Http\Requests\SubjectRequest;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Subject::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
        ];

        return Inertia::render('Subjects/Index', [
            'subjects' => Subject::query()
                ->withCount('classSubjects')
                ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
                ->when(ActiveStatus::tryFrom($filters['status']), fn ($query, $status) => $query->where('status', $status))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Subject $subject) => [...$subject->summary(), 'classes_count' => $subject->class_subjects_count]),
            'filters' => $filters,
            'statuses' => ActiveStatus::options(),
            'can' => ['create' => $request->user()->can('create', Subject::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Subject::class);

        return Inertia::render('Subjects/Create', ['statuses' => ActiveStatus::options()]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        return redirect()->route('subjects.show', $subject)->with('success', "{$subject->name} was added.");
    }

    public function show(Request $request, Subject $subject): Response
    {
        $this->authorize('view', $subject);

        return Inertia::render('Subjects/Show', [
            'subject' => $subject->summary(),
            'classSubjects' => $subject->classSubjects()
                ->with(['subject', 'schoolClass', 'teacher', 'academicTerm'])
                ->latest('id')
                ->get()
                ->map(fn (ClassSubject $classSubject) => $classSubject->summary()),
            'can' => [
                'update' => $request->user()->can('update', $subject),
                'delete' => $request->user()->can('delete', $subject),
            ],
        ]);
    }

    public function edit(Subject $subject): Response
    {
        $this->authorize('update', $subject);

        return Inertia::render('Subjects/Edit', [
            'subject' => $subject->summary(),
            'statuses' => ActiveStatus::options(),
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->route('subjects.show', $subject)->with('success', "{$subject->name} was updated.");
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        if ($subject->hasDependents() && $request->user()->can('subjects.delete')) {
            return back()->with('error', "{$subject->name} has been taught to classes. Make it inactive instead to keep its history.");
        }

        $this->authorize('delete', $subject);

        $subject->delete();

        return redirect()->route('subjects.index')->with('success', "{$subject->name} was deleted.");
    }
}
