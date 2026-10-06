<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Http\Requests\Teachers\StoreTeacherRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    private const PROFILE_FIELDS = [
        'employee_number', 'first_name', 'middle_name', 'last_name',
        'birth_date', 'gender', 'contact_number', 'specialization',
    ];

    public function __construct(private TeacherService $teachers) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Teacher::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
        ];

        $teachers = Teacher::query()
            ->with('user:id,email')
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when(TeacherStatus::tryFrom($filters['status']), fn ($query, $status) => $query->where('status', $status))
            ->orderByName()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Teacher $teacher) => $teacher->summary());

        return Inertia::render('Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $filters,
            'statuses' => TeacherStatus::options(),
            'can' => ['create' => $request->user()->can('create', Teacher::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Teacher::class);

        return Inertia::render('Teachers/Create', $this->formOptions());
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $teacher = $this->teachers->create(
            $request->safe()->only(self::PROFILE_FIELDS),
            $request->safe()->only(['email', 'password']),
            TeacherStatus::from($request->validated('status')),
        );

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('success', "{$teacher->full_name} was added.");
    }

    public function show(Request $request, Teacher $teacher): Response
    {
        $this->authorize('view', $teacher);

        $teacher->load('user:id,email,last_login_at');

        return Inertia::render('Teachers/Show', [
            'teacher' => $this->details($teacher),
            // Classes the teacher is or was attached to, newest first.
            'assignments' => $teacher->classAssignments()
                ->with(['schoolClass', 'academicYear'])
                ->orderByDesc('assigned_at')
                ->get()
                ->map(fn (ClassTeacher $assignment) => [
                    'id' => $assignment->id,
                    'role_label' => $assignment->role->label(),
                    'class' => ['id' => $assignment->schoolClass->id, 'name' => $assignment->schoolClass->name],
                    'academic_year' => $assignment->academicYear->name,
                    'assigned_at' => $assignment->assigned_at->toIso8601String(),
                    'ended_at' => $assignment->ended_at?->toIso8601String(),
                ]),
            // Subjects the teacher teaches, in every class and term.
            'classSubjects' => $teacher->classSubjects()
                ->with(['subject', 'schoolClass', 'teacher', 'academicTerm'])
                ->latest('id')
                ->get()
                ->map(fn (ClassSubject $classSubject) => $classSubject->summary()),
            'statuses' => TeacherStatus::options(),
            'can' => [
                'update' => $request->user()->can('update', $teacher),
                'delete' => $request->user()->can('delete', $teacher),
            ],
        ]);
    }

    public function edit(Teacher $teacher): Response
    {
        $this->authorize('update', $teacher);

        $teacher->load('user:id,email');

        return Inertia::render('Teachers/Edit', [
            ...$this->formOptions(),
            'teacher' => $this->details($teacher),
        ]);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->teachers->update(
            $teacher,
            $request->safe()->only(self::PROFILE_FIELDS),
            $request->safe()->only(['email', 'password']),
        );

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('success', "{$teacher->full_name}'s profile was updated.");
    }

    public function updateStatus(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorize('update', $teacher);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TeacherStatus::class)],
        ]);

        $status = TeacherStatus::from($validated['status']);
        $this->teachers->changeStatus($teacher, $status);

        return back()->with('success', "{$teacher->full_name} is now {$status->label()}.");
    }

    public function destroy(Request $request, Teacher $teacher): RedirectResponse
    {
        if ($teacher->hasHistory() && $request->user()->can('teachers.delete')) {
            return back()->with('error', "{$teacher->full_name} has class history, so the record is kept. Change their status instead.");
        }

        $this->authorize('delete', $teacher);

        $this->teachers->delete($teacher);

        return redirect()
            ->route('teachers.index')
            ->with('success', "{$teacher->full_name} was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Teacher $teacher): array
    {
        return [
            ...$teacher->summary(),
            'first_name' => $teacher->first_name,
            'middle_name' => $teacher->middle_name,
            'last_name' => $teacher->last_name,
            'birth_date' => $teacher->birth_date?->toDateString(),
            'gender' => $teacher->gender?->value,
            'gender_label' => $teacher->gender?->label(),
            'contact_number' => $teacher->contact_number,
            'last_login_at' => $teacher->user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'genders' => Gender::options(),
            'statuses' => TeacherStatus::options(),
        ];
    }
}
