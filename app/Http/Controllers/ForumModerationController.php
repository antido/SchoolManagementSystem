<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\ForumCategory;
use App\Models\ForumPostReport;
use App\Models\ForumTopic;
use App\Services\ForumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The report queue, and managing forum categories.
 */
class ForumModerationController extends Controller
{
    public function __construct(private readonly ForumService $forum) {}

    /**
     * Reported posts: pending first, then a history of handled reports.
     */
    public function reports(Request $request): Response
    {
        $this->authorize('moderate', ForumTopic::class);

        $status = ReportStatus::tryFrom((string) $request->query('status')) ?? ReportStatus::Pending;

        return Inertia::render('Forum/Reports', [
            'status' => $status->value,
            'reports' => ForumPostReport::query()
                ->where('status', $status)
                ->with(['post.author', 'post.topic', 'reporter', 'resolver'])
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ForumPostReport $report) => [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'status' => $report->status->value,
                    'reported_at' => $report->created_at->toIso8601String(),
                    'reporter' => $report->reporter->name,
                    'resolver' => $report->resolver?->name,
                    'resolved_at' => $report->resolved_at?->toIso8601String(),
                    'post' => [
                        'id' => $report->post->id,
                        'body' => $report->post->body,
                        'removed' => $report->post->trashed(),
                        'author' => $report->post->author->name,
                        'topic' => ['id' => $report->post->topic->id, 'title' => $report->post->topic->title],
                    ],
                ]),
        ]);
    }

    /**
     * Remove the reported post, or dismiss the report.
     */
    public function resolve(Request $request, ForumPostReport $report): RedirectResponse
    {
        $this->authorize('moderate', ForumTopic::class);

        $validated = $request->validate(['action' => ['required', Rule::in(['remove', 'dismiss'])]]);

        if ($report->status !== ReportStatus::Pending) {
            return back()->with('error', 'This report was already handled.');
        }

        $this->forum->resolveReport($report, $validated['action'] === 'remove', $request->user());

        return back()->with('success', $validated['action'] === 'remove' ? 'The post was removed.' : 'The report was dismissed.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('manageCategories', ForumTopic::class);

        ForumCategory::create($this->validateCategory($request));

        return back()->with('success', 'The category was added.');
    }

    public function updateCategory(Request $request, ForumCategory $category): RedirectResponse
    {
        $this->authorize('manageCategories', ForumTopic::class);

        $category->update($this->validateCategory($request, $category));

        return back()->with('success', 'The category was updated.');
    }

    /**
     * Only empty categories can be deleted.
     */
    public function destroyCategory(ForumCategory $category): RedirectResponse
    {
        $this->authorize('manageCategories', ForumTopic::class);

        if ($category->hasDependents()) {
            return back()->with('error', 'This category has topics, so it is kept.');
        }

        $category->delete();

        return back()->with('success', 'The category was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?ForumCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('forum_categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        return [...$data, 'order' => $data['order'] ?? 0];
    }
}
