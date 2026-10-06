<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Reports\Report;
use App\Reports\ReportExporter;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Lists reports, previews them with filters, and exports them. The report
 * classes in app/Reports produce the data; this controller only wires
 * requests to them.
 */
class ReportController extends Controller
{
    /** Rows shown on screen; exports always contain everything. */
    private const PREVIEW_ROWS = 200;

    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly ReportExporter $exporter,
    ) {}

    public function index(Request $request): Response
    {
        $available = $this->reports->availableTo($request->user());

        abort_if($available === [], 403);

        return Inertia::render('Reports/Index', [
            'groups' => collect($available)
                ->groupBy(fn (Report $report) => $report->group())
                ->map(fn ($reports, string $group) => [
                    'name' => $group,
                    'reports' => $reports->map(fn (Report $report) => $this->describe($report))->values(),
                ])
                ->values(),
        ]);
    }

    public function show(Request $request, string $report): Response
    {
        $report = $this->authorizedReport($request, $report);
        $filters = ReportFilters::fromRequest($request);
        $user = $request->user();

        $ready = $this->hasRequired($report, $filters);
        abort_if($ready && ! $report->allowsFilters($user, $filters), 403);

        $result = $ready ? $report->run($filters, $user) : null;

        return Inertia::render('Reports/Show', [
            'report' => $this->describe($report),
            'values' => $request->only(['academic_year_id', 'academic_term_id', 'school_class_id', 'class_subject_id', 'student_id', 'from', 'to']),
            'options' => $this->options($report, $request, $filters),
            'studentOptions' => Inertia::optional(fn () => $this->studentOptions($request)),
            'result' => $result ? [
                'title' => $result->title,
                'subtitle' => $result->subtitle,
                'columns' => $result->columns,
                'rows' => array_slice($result->rows, 0, self::PREVIEW_ROWS),
                'total_rows' => count($result->rows),
                'summary' => $result->summary,
            ] : null,
            'formats' => ReportExporter::FORMATS,
            'can' => ['export' => $user->can('reports.export')],
        ]);
    }

    public function export(Request $request, string $report, string $format): HttpResponse
    {
        abort_unless(in_array($format, ReportExporter::FORMATS, true), 404);
        abort_unless($request->user()->can('reports.export'), 403);

        $report = $this->authorizedReport($request, $report);
        $filters = ReportFilters::fromRequest($request);

        abort_unless($this->hasRequired($report, $filters), 422, 'Choose the required filters first.');
        abort_unless($report->allowsFilters($request->user(), $filters), 403);

        return $this->exporter->download($report->run($filters, $request->user()), $format);
    }

    private function authorizedReport(Request $request, string $key): Report
    {
        $report = $this->reports->find($key) ?? abort(404);

        abort_unless($report->allows($request->user()), 403);

        return $report;
    }

    private function hasRequired(Report $report, ReportFilters $filters): bool
    {
        return collect($report->required())->every(fn (string $filter) => $filters->has($filter));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Report $report): array
    {
        return [
            'key' => $report->key(),
            'title' => $report->title(),
            'description' => $report->description(),
            'filters' => $report->filters(),
            'required' => $report->required(),
        ];
    }

    /**
     * Choices for the filters the report uses, limited to what the person may see.
     *
     * @return array<string, mixed>
     */
    private function options(Report $report, Request $request, ReportFilters $filters): array
    {
        $user = $request->user();
        $uses = fn (string $filter) => in_array($filter, $report->filters(), true);

        return [
            'academicYears' => $uses('academic_year_id')
                ? AcademicYear::query()->orderByDesc('start_date')->get()->map(fn (AcademicYear $year) => ['value' => $year->id, 'label' => $year->name])
                : [],
            'academicTerms' => $uses('academic_term_id')
                ? AcademicTerm::query()->with('academicYear')->orderByDesc('start_date')->get()
                    ->map(fn (AcademicTerm $term) => ['value' => $term->id, 'label' => "{$term->name} ({$term->academicYear->name})"])
                : [],
            'classes' => $uses('school_class_id')
                ? SchoolClass::query()->visibleTo($user)->with('academicYear')->latest('id')->limit(300)->get()
                    ->map(fn (SchoolClass $class) => ['value' => $class->id, 'label' => "{$class->name} ({$class->academicYear->name})"])
                : [],
            'classSubjects' => $uses('class_subject_id')
                ? ClassSubject::query()->managedBy($user)->with(['subject', 'schoolClass', 'academicTerm'])->latest('id')->limit(300)->get()
                    ->map(fn (ClassSubject $classSubject) => ['value' => $classSubject->id, 'label' => $classSubject->label()])
                : [],
            // Named only when the person may see the student, whatever id was typed in the address.
            'student' => $filters->student && Student::query()->visibleTo($user)->whereKey($filters->student->id)->exists() ? ['value' => $filters->student->id, 'label' => "{$filters->student->sortable_name} ({$filters->student->student_number})"] : null,
        ];
    }

    /**
     * Students matching the picker's search, among those the person may see.
     *
     * @return list<array{value: int, label: string}>
     */
    private function studentOptions(Request $request): array
    {
        $search = trim((string) $request->query('student_search'));

        if (mb_strlen($search) < 2) {
            return [];
        }

        return Student::query()
            ->visibleTo($request->user())
            ->search($search)
            ->orderByName()
            ->limit(15)
            ->get()
            ->map(fn (Student $student) => ['value' => $student->id, 'label' => "{$student->sortable_name} ({$student->student_number})"])
            ->all();
    }
}
