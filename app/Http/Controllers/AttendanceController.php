<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\RecordAttendanceRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Attendance sheets, class summaries and each student's attendance.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Students go to their own attendance; staff see the classes they can open.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('attendance.view'), 403);

        if ($user->student !== null) {
            return redirect()->route('attendance.student', $user->student);
        }

        $activeYear = AcademicYear::active();

        return Inertia::render('Attendance/Index', [
            'classes' => SchoolClass::query()
                ->visibleTo($user)
                ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
                ->with('academicYear')
                ->withCount(['enrollments' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $class) => [
                    ...$class->summary(),
                    'students_count' => $class->enrollments_count,
                    'can_record' => $user->can('record', [Attendance::class, $class])
                        || $this->recordableSubjects($user, $class)->isNotEmpty(),
                ]),
        ]);
    }

    /**
     * The attendance sheet of a class (or one of its subjects) for a day.
     */
    public function sheet(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('viewClass', [Attendance::class, $schoolClass]);

        $user = $request->user();
        $date = $this->dateFrom($request->query('date')) ?? today();
        $classSubject = $schoolClass->classSubjects()->whereKey($request->integer('class_subject'))->first();

        $records = $this->attendance->sheetQuery($schoolClass, $classSubject, $date)->get()->keyBy('student_id');

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $schoolClass->id))
            ->orderByName()
            ->get();

        $schoolClass->load('academicYear');

        return Inertia::render('Attendance/Sheet', [
            'schoolClass' => $schoolClass->summary(),
            'yearRange' => [
                'start' => $schoolClass->academicYear->start_date->toDateString(),
                'end' => $schoolClass->academicYear->end_date->toDateString(),
            ],
            'date' => $date->toDateString(),
            'classSubjectId' => $classSubject?->id,
            'subjects' => $schoolClass->classSubjects()->with(['subject', 'academicTerm'])->get()
                ->map(fn (ClassSubject $option) => ['value' => $option->id, 'label' => "{$option->subject->name} ({$option->academicTerm->name})"]),
            'rows' => $students->map(fn (Student $student) => [
                'student' => ['id' => $student->id, 'name' => $student->sortable_name, 'student_number' => $student->student_number],
                'status' => $records->get($student->id)?->status->value,
                'remarks' => $records->get($student->id)?->remarks,
            ])->values(),
            'recorded' => $records->isNotEmpty(),
            'statuses' => AttendanceStatus::options(),
            'can' => ['record' => $user->can('record', [Attendance::class, $schoolClass, $classSubject])],
        ]);
    }

    public function store(RecordAttendanceRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $date = Carbon::parse($request->validated('date'));

        $count = $this->attendance->record(
            $schoolClass,
            $request->classSubject(),
            $date,
            $request->validated('records'),
            $request->user(),
        );

        return back()->with('success', "Attendance for {$date->format('M j, Y')} was saved ({$count} ".($count === 1 ? 'student' : 'students').').');
    }

    /**
     * Each student's attendance in a class over a period.
     */
    public function summary(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('viewClass', [Attendance::class, $schoolClass]);

        [$from, $to] = $this->period($request);
        $classSubject = $schoolClass->classSubjects()->whereKey($request->integer('class_subject'))->first();

        $query = Attendance::query()
            ->where('school_class_id', $schoolClass->id)
            ->when(
                $classSubject,
                fn ($query) => $query->where('class_subject_id', $classSubject->id),
                fn ($query) => $query->whereNull('class_subject_id'),
            )
            // Through the end of the last day, as some databases store the date with a time.
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString().' 23:59:59']);

        $summaries = $this->attendance->summaryByStudent($query);
        $days = (clone $query)->distinct()->count('attendance_date');

        $students = Student::query()
            ->where(fn ($query) => $query
                ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $schoolClass->id))
                ->orWhereIn('id', array_keys($summaries)))
            ->orderByName()
            ->get();

        $totals = collect($summaries);

        return Inertia::render('Attendance/Summary', [
            'schoolClass' => $schoolClass->load('academicYear')->summary(),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'class_subject' => $classSubject?->id],
            'subjects' => $schoolClass->classSubjects()->with(['subject', 'academicTerm'])->get()
                ->map(fn (ClassSubject $option) => ['value' => $option->id, 'label' => "{$option->subject->name} ({$option->academicTerm->name})"]),
            'days' => $days,
            'overall' => AttendanceService::summarise($totals->sum('present'), $totals->sum('absent'), $totals->sum('late'), $totals->sum('excused')),
            'rows' => $students->map(fn (Student $student) => [
                'student' => ['id' => $student->id, 'name' => $student->sortable_name, 'student_number' => $student->student_number],
                'summary' => $summaries[$student->id] ?? AttendanceService::summarise(0, 0, 0, 0),
            ])->values(),
        ]);
    }

    /**
     * One student's attendance: totals and day-by-day history.
     */
    public function student(Request $request, Student $student): Response
    {
        $this->authorize('viewStudent', [Attendance::class, $student]);

        [$from, $to] = $this->period($request);

        $query = Attendance::query()
            ->where('student_id', $student->id)
            // Through the end of the last day, as some databases store the date with a time.
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString().' 23:59:59']);

        return Inertia::render('Attendance/Student', [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'student_number' => $student->student_number],
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $this->attendance->summaryByStudent(clone $query)[$student->id] ?? AttendanceService::summarise(0, 0, 0, 0),
            'history' => $query
                ->with(['schoolClass', 'classSubject.subject'])
                ->latest('attendance_date')
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'date' => $attendance->attendance_date->toDateString(),
                    'status' => $attendance->status->value,
                    'remarks' => $attendance->remarks,
                    'class' => $attendance->schoolClass->name,
                    'subject' => $attendance->classSubject?->subject->name,
                ]),
            'isOwn' => $request->user()->student?->id === $student->id,
        ]);
    }

    /**
     * Subjects of the class whose attendance this person may record.
     *
     * @return Collection<int, ClassSubject>
     */
    private function recordableSubjects(User $user, SchoolClass $class): Collection
    {
        return $class->classSubjects()->get()->filter(fn (ClassSubject $classSubject) => $user->can('record', [Attendance::class, $class, $classSubject]));
    }

    /**
     * The period asked for, defaulting to the active term so far (or the last 30 days).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $term = AcademicTerm::active();

        $from = $this->dateFrom($request->query('from')) ?? ($term ? $term->start_date->copy() : today()->subDays(30));
        $to = $this->dateFrom($request->query('to')) ?? today();

        return $from->lte($to) ? [$from, $to] : [$to, $from];
    }

    private function dateFrom(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return rescue(fn () => Carbon::createFromFormat('Y-m-d', $value)->startOfDay(), null, report: false);
    }
}
