<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentAward;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * The profile fields saved on the student record.
     */
    private const PROFILE_FIELDS = [
        'student_number', 'first_name', 'middle_name', 'last_name',
        'birth_date', 'gender', 'address', 'contact_number',
    ];

    public function __construct(private StudentService $students) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
        ];

        $students = Student::query()
            ->visibleTo($request->user())
            ->with('user:id,email')
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when(StudentStatus::tryFrom($filters['status']), fn ($query, $status) => $query->where('status', $status))
            ->orderByName()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Student $student) => $student->summary());

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $filters,
            'statuses' => StudentStatus::options(),
            'can' => ['create' => $request->user()->can('create', Student::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Student::class);

        return Inertia::render('Students/Create', $this->formOptions());
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = $this->students->create(
            $request->safe()->only(self::PROFILE_FIELDS),
            $request->safe()->only(['email', 'password']),
            StudentStatus::from($request->validated('status')),
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', "{$student->full_name} was added.");
    }

    public function show(Request $request, Student $student): Response
    {
        $this->authorize('view', $student);

        $student->load('user:id,email,last_login_at');

        return Inertia::render('Students/Show', [
            'student' => $this->details($student),
            // Every class the student has been in, newest first.
            'enrollments' => $request->user()->can('viewAny', Enrollment::class) || $request->user()->id === $student->user_id
                ? $student->enrollments()
                    ->with(['schoolClass', 'academicYear'])
                    ->orderByDesc('enrolled_at')
                    ->get()
                    ->map(fn (Enrollment $enrollment) => [
                        'id' => $enrollment->id,
                        'class' => ['id' => $enrollment->schoolClass->id, 'name' => $enrollment->schoolClass->name],
                        'academic_year' => $enrollment->academicYear->name,
                        'status' => $enrollment->status->value,
                        'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                        'ended_at' => $enrollment->ended_at?->toIso8601String(),
                    ])
                : [],
            // Awards received. Revoked ones are shown to staff only, marked as such.
            'awards' => ! $request->user()->can('awards.view') ? [] : $student->awards()
                ->when($request->user()->id === $student->user_id, fn ($query) => $query->notRevoked())
                ->with(['award.attachments', 'academicYear', 'schoolClass', 'awardedBy'])
                ->latest('awarded_on')
                ->get()
                ->map(fn (StudentAward $studentAward) => $studentAward->summary()),
            'statuses' => StudentStatus::options(),
            'can' => [
                'update' => $request->user()->can('update', $student),
                'delete' => $request->user()->can('delete', $student),
            ],
        ]);
    }

    public function edit(Student $student): Response
    {
        $this->authorize('update', $student);

        $student->load('user:id,email');

        return Inertia::render('Students/Edit', [
            ...$this->formOptions(),
            'student' => $this->details($student),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update(
            $student,
            $request->safe()->only(self::PROFILE_FIELDS),
            $request->safe()->only(['email', 'password']),
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', "{$student->full_name}'s profile was updated.");
    }

    /**
     * Change the student's status, for example to graduated.
     */
    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(StudentStatus::class)],
        ]);

        $status = StudentStatus::from($validated['status']);
        $this->students->changeStatus($student, $status);

        return back()->with('success', "{$student->full_name} is now {$status->value}.");
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        if ($student->hasHistory() && $request->user()->can('students.delete')) {
            return back()->with('error', "{$student->full_name} has school history, so the record is kept. Change their status instead.");
        }

        $this->authorize('delete', $student);

        $this->students->delete($student);

        return redirect()
            ->route('students.index')
            ->with('success', "{$student->full_name} was deleted.");
    }

    /**
     * Everything the profile page and edit form need. Load `user` first.
     *
     * @return array<string, mixed>
     */
    private function details(Student $student): array
    {
        return [
            ...$student->summary(),
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'birth_date' => $student->birth_date?->toDateString(),
            'gender' => $student->gender?->value,
            'gender_label' => $student->gender?->label(),
            'address' => $student->address,
            'contact_number' => $student->contact_number,
            'last_login_at' => $student->user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'genders' => Gender::options(),
            'statuses' => StudentStatus::options(),
        ];
    }
}
