<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School performance at a glance, for staff who may open reports.
 * Teachers see figures for their own classes only.
 */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $user = $request->user();
        abort_unless($user->student === null && $user->can('reports.view') && $user->can('grades.view'), 403);

        $year = $request->filled('academic_year_id')
            ? AcademicYear::find($request->integer('academic_year_id'))
            : AcademicYear::active();

        $class = $request->filled('school_class_id')
            ? SchoolClass::query()->visibleTo($user)->find($request->integer('school_class_id'))
            : null;

        // Figures are kept for five minutes per person, year and class: the
        // page is opened often and the numbers move slowly. They are cached as
        // plain arrays: the cache does not restore objects such as collections
        // (cache.serializable_classes is off).
        $figures = Cache::remember(
            "analytics:{$user->id}:".($year?->id ?? 'all').':'.($class?->id ?? 'all'),
            now()->addMinutes(5),
            fn () => collect($analytics->build($user, $year, $class))->toArray(),
        );

        return Inertia::render('Analytics/Index', [
            ...$figures,
            'filters' => [
                'academic_year_id' => $year?->id,
                'school_class_id' => $class?->id,
            ],
            'options' => [
                'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get()
                    ->map(fn (AcademicYear $option) => ['value' => $option->id, 'label' => $option->name]),
                'classes' => SchoolClass::query()->visibleTo($user)
                    ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (SchoolClass $option) => ['value' => $option->id, 'label' => $option->name]),
            ],
            'thresholds' => [
                'attendance' => AnalyticsService::ATTENDANCE_THRESHOLD,
                'missing' => AnalyticsService::MISSING_THRESHOLD,
            ],
        ]);
    }
}
