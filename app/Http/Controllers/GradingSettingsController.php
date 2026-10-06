<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\GradingScaleRequest;
use App\Http\Requests\GradingSystemRequest;
use App\Models\GradingScale;
use App\Models\GradingSystem;
use App\Services\GradingConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School-wide grading settings: grading systems (component weights) and
 * grading scales (descriptors and passing mark).
 */
class GradingSettingsController extends Controller
{
    public function __construct(private readonly GradingConfigService $config) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage', GradingSystem::class);

        $user = $request->user();

        return Inertia::render('Grading/Index', [
            'systems' => GradingSystem::query()->with(['components', 'scale'])->withCount('classSubjects')->orderBy('name')->get()
                ->map(fn (GradingSystem $system) => [
                    ...$system->summary(),
                    'class_subjects_count' => $system->class_subjects_count,
                    'can_delete' => $user->can('delete', $system),
                ]),
            'scales' => GradingScale::query()->with('bands')->orderBy('name')->get()
                ->map(fn (GradingScale $scale) => [...$scale->summary(), 'can_delete' => $user->can('deleteScale', [GradingSystem::class, $scale])]),
        ]);
    }

    public function createSystem(): Response
    {
        $this->authorize('manage', GradingSystem::class);

        return Inertia::render('Grading/SystemForm', $this->systemFormOptions());
    }

    public function storeSystem(GradingSystemRequest $request): RedirectResponse
    {
        $system = $this->config->saveSystem(null, $request->validated());

        return redirect()->route('grading.index')->with('success', "{$system->name} was added.");
    }

    public function editSystem(GradingSystem $gradingSystem): Response
    {
        $this->authorize('manage', GradingSystem::class);

        return Inertia::render('Grading/SystemForm', [
            'system' => $gradingSystem->load(['components', 'scale'])->summary() + ['grading_scale_id' => $gradingSystem->grading_scale_id],
            ...$this->systemFormOptions(),
        ]);
    }

    public function updateSystem(GradingSystemRequest $request, GradingSystem $gradingSystem): RedirectResponse
    {
        $this->config->saveSystem($gradingSystem, $request->validated());

        return redirect()->route('grading.index')->with('success', "{$gradingSystem->name} was updated. Compute grades again to apply the new weights.");
    }

    public function destroySystem(GradingSystem $gradingSystem): RedirectResponse
    {
        $this->authorize('delete', $gradingSystem);

        $gradingSystem->delete();

        return back()->with('success', "{$gradingSystem->name} was deleted.");
    }

    public function createScale(): Response
    {
        $this->authorize('manage', GradingSystem::class);

        return Inertia::render('Grading/ScaleForm');
    }

    public function storeScale(GradingScaleRequest $request): RedirectResponse
    {
        $scale = $this->config->saveScale(null, $request->validated());

        return redirect()->route('grading.index')->with('success', "{$scale->name} was added.");
    }

    public function editScale(GradingScale $gradingScale): Response
    {
        $this->authorize('manage', GradingSystem::class);

        return Inertia::render('Grading/ScaleForm', ['scale' => $gradingScale->load('bands')->summary()]);
    }

    public function updateScale(GradingScaleRequest $request, GradingScale $gradingScale): RedirectResponse
    {
        $this->config->saveScale($gradingScale, $request->validated());

        return redirect()->route('grading.index')->with('success', "{$gradingScale->name} was updated. Compute grades again to apply it.");
    }

    public function destroyScale(Request $request, GradingScale $gradingScale): RedirectResponse
    {
        $this->authorize('deleteScale', [GradingSystem::class, $gradingScale]);

        $gradingScale->delete();

        return back()->with('success', "{$gradingScale->name} was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function systemFormOptions(): array
    {
        return [
            'scales' => GradingScale::query()->orderBy('name')->get()->map(fn (GradingScale $scale) => ['value' => $scale->id, 'label' => $scale->name]),
            'activityTypes' => ActivityType::options(),
        ];
    }
}
