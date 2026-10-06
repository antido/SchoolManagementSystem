<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The FAQ page for readers, and managing its entries.
 */
class FaqController extends Controller
{
    /**
     * Published questions, grouped by category, with a search.
     * Open to guests when config('school.faq_public') is on.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! config('school.faq_public')) {
            if ($user === null) {
                return redirect()->guest(route('login'));
            }

            abort_unless($user->can('faqs.view'), 403);
        }

        $search = trim((string) $request->query('search'));

        $faqs = Faq::query()
            ->published()
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $categories = FaqCategory::query()->orderBy('order')->orderBy('name')->get();

        $groups = $categories
            ->map(fn (FaqCategory $category) => [
                'name' => $category->name,
                'faqs' => $faqs->where('faq_category_id', $category->id)->map(fn (Faq $faq) => $faq->only(['id', 'question', 'answer']))->values(),
            ])
            ->push(['name' => $categories->isEmpty() ? null : 'Other questions', 'faqs' => $faqs->whereNull('faq_category_id')->map(fn (Faq $faq) => $faq->only(['id', 'question', 'answer']))->values()])
            ->filter(fn (array $group) => $group['faqs']->isNotEmpty())
            ->values();

        return Inertia::render('Faq/Index', [
            'groups' => $groups,
            'search' => $search,
            'can' => ['manage' => (bool) $user?->can('faqs.manage')],
        ]);
    }

    /**
     * Every entry, published or not, for editors.
     */
    public function manage(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Faq/Manage', [
            'categories' => FaqCategory::query()->withCount('faqs')->orderBy('order')->orderBy('name')->get()
                ->map(fn (FaqCategory $category) => [...$category->only(['id', 'name', 'order']), 'faqs_count' => $category->faqs_count]),
            'faqs' => Faq::query()->with('category')->orderBy('faq_category_id')->orderBy('order')->orderBy('id')->get()
                ->map(fn (Faq $faq) => [...$faq->summary(), 'category' => $faq->category?->name]),
        ]);
    }

    public function create(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Faq/Form', ['categories' => $this->categoryOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $faq = new Faq($this->validateFaq($request));
        $faq->order = (int) Faq::where('faq_category_id', $faq->faq_category_id)->max('order') + 1;
        $faq->save();

        return redirect()->route('faqs.manage')->with('success', 'The question was added.');
    }

    public function edit(Faq $faq): Response
    {
        $this->authorizeManage();

        return Inertia::render('Faq/Form', ['faq' => $faq->summary(), 'categories' => $this->categoryOptions()]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $this->authorizeManage();

        $faq->fill($this->validateFaq($request));

        // Moving to another category puts it at the end there.
        if ($faq->isDirty('faq_category_id')) {
            $faq->order = (int) Faq::where('faq_category_id', $faq->faq_category_id)->max('order') + 1;
        }

        $faq->save();

        return redirect()->route('faqs.manage')->with('success', 'The question was updated.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorizeManage();

        $faq->delete();

        return back()->with('success', 'The question was deleted.');
    }

    /**
     * Publish or unpublish.
     */
    public function togglePublished(Faq $faq): RedirectResponse
    {
        $this->authorizeManage();

        $faq->update(['is_published' => ! $faq->is_published]);

        return back()->with('success', $faq->is_published ? 'The question is now published.' : 'The question is now hidden.');
    }

    /**
     * Swap with the question before or after it in the same category.
     */
    public function move(Request $request, Faq $faq): RedirectResponse
    {
        $this->authorizeManage();

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($faq, $direction): void {
            $siblings = Faq::query()->where('faq_category_id', $faq->faq_category_id)->orderBy('order')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $siblings->search(fn (Faq $other) => $other->is($faq));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($siblings->has($target)) {
                [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];
                $siblings->each(fn (Faq $other, int $position) => $other->forceFill(['order' => $position + 1])->save());
            }
        });

        return back();
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        FaqCategory::create($this->validateCategory($request));

        return back()->with('success', 'The category was added.');
    }

    public function updateCategory(Request $request, FaqCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        $category->update($this->validateCategory($request, $category));

        return back()->with('success', 'The category was updated.');
    }

    /**
     * Its questions stay, listed under "Other questions".
     */
    public function destroyCategory(FaqCategory $category): RedirectResponse
    {
        $this->authorizeManage();

        $category->delete();

        return back()->with('success', 'The category was deleted. Its questions are now under "Other questions".');
    }

    private function authorizeManage(): void
    {
        abort_unless(request()->user()?->can('faqs.manage'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFaq(Request $request): array
    {
        return $request->validate([
            'faq_category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:10000'],
            'is_published' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?FaqCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('faq_categories')->ignore($category)],
            'order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        return [...$data, 'order' => $data['order'] ?? 0];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function categoryOptions(): array
    {
        return FaqCategory::query()->orderBy('order')->orderBy('name')->get()
            ->map(fn (FaqCategory $category) => ['value' => $category->id, 'label' => $category->name])
            ->all();
    }
}
