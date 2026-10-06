<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Enums\ReportStatus;
use App\Models\Attachment;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumPostReport;
use App\Models\ForumTopic;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\ForumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The discussion forum: categories, topics and posts.
 */
class ForumController extends Controller
{
    private const POSTS_PER_PAGE = 20;

    public function __construct(private readonly ForumService $forum) {}

    /**
     * The categories, or topics matching a search.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ForumTopic::class);

        $user = $request->user();
        $search = trim((string) $request->query('search'));

        return Inertia::render('Forum/Index', [
            'categories' => ForumCategory::query()
                ->withCount('topics')
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->map(fn (ForumCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'order' => $category->order,
                    'topics_count' => $category->topics_count,
                    'latest' => $category->topics()->latest('last_posted_at')->first(['id', 'title', 'last_posted_at'])?->only(['id', 'title', 'last_posted_at']),
                    'can_delete' => ! $category->hasDependents(),
                ]),
            'search' => $search,
            'results' => $search === '' ? null : ForumTopic::query()
                ->search($search)
                ->with(['author', 'category'])
                ->inListOrder()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ForumTopic $topic) => $this->topicSummary($topic)),
            'pendingReports' => $user->can('moderate', ForumTopic::class)
                ? ForumPostReport::query()->where('status', ReportStatus::Pending)->count()
                : null,
            'can' => ['manageCategories' => $user->can('manageCategories', ForumTopic::class)],
        ]);
    }

    public function category(Request $request, ForumCategory $category): Response
    {
        $this->authorize('viewAny', ForumTopic::class);

        return Inertia::render('Forum/Category', [
            'category' => $category->only(['id', 'name', 'description']),
            'topics' => $category->topics()
                ->with('author')
                ->inListOrder()
                ->paginate(20)
                ->through(fn (ForumTopic $topic) => $this->topicSummary($topic)),
            'can' => ['create' => $request->user()->can('create', ForumTopic::class)],
        ]);
    }

    public function createTopic(ForumCategory $category): Response
    {
        $this->authorize('create', ForumTopic::class);

        return Inertia::render('Forum/CreateTopic', ['category' => $category->only(['id', 'name'])]);
    }

    public function storeTopic(Request $request, ForumCategory $category): RedirectResponse
    {
        $this->authorize('create', ForumTopic::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            ...AttachmentService::rules(),
        ]);

        $topic = $this->forum->createTopic($category, $request->user(), $validated['title'], $validated['body'], $request->file('attachments', []));

        return redirect()->route('forum.topics.show', $topic)->with('success', 'Your topic was posted.');
    }

    public function showTopic(Request $request, ForumTopic $topic): Response
    {
        $this->authorize('view', $topic);

        $user = $request->user();
        $topic->load(['author', 'category']);

        $posts = $topic->posts()
            ->with(['author.roles', 'reactions', 'topic', 'attachments'])
            ->paginate(self::POSTS_PER_PAGE);

        $openingId = ForumPost::withTrashed()->where('forum_topic_id', $topic->id)->orderBy('created_at')->orderBy('id')->value('id');
        $reported = ForumPostReport::query()->where('user_id', $user->id)->whereIn('forum_post_id', $posts->pluck('id'))->pluck('forum_post_id')->all();

        return Inertia::render('Forum/Topic', [
            'topic' => [
                ...$this->topicSummary($topic),
                'category' => $topic->category->only(['id', 'name']),
                'deleted' => $topic->trashed(),
            ],
            'posts' => $posts->through(fn (ForumPost $post) => $this->postSummary($post, $user, $post->id === $openingId, in_array($post->id, $reported, true))),
            'reactionTypes' => array_map(fn (ReactionType $type) => ['value' => $type->value, 'label' => $type->label()], ReactionType::cases()),
            'can' => [
                'reply' => $user->can('reply', $topic),
                'moderate' => $user->can('moderate', ForumTopic::class),
                'delete' => $user->can('delete', $topic),
            ],
        ]);
    }

    public function reply(Request $request, ForumTopic $topic): RedirectResponse
    {
        $this->authorize('reply', $topic);

        $validated = $request->validate(['body' => ['required', 'string', 'max:10000'], ...AttachmentService::rules()]);

        $post = $this->forum->reply($topic, $request->user(), $validated['body'], $request->file('attachments', []));

        // Go to the page the new reply is on.
        $page = (int) ceil(ForumPost::withTrashed()->where('forum_topic_id', $topic->id)->count() / self::POSTS_PER_PAGE);

        return redirect()->to(route('forum.topics.show', [$topic, 'page' => $page]).'#post-'.$post->id)->with('success', 'Your reply was posted.');
    }

    public function updatePost(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $this->forum->editPost($post, $validated['body']);

        return back()->with('success', 'Your post was updated.');
    }

    public function destroyPost(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $opening = $post->isOpening();
        $this->forum->removePost($post, $request->user());

        return $opening
            ? redirect()->route('forum.categories.show', $post->topic->forum_category_id)->with('success', 'The topic was removed.')
            : back()->with('success', 'The post was removed.');
    }

    public function react(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('react', $post);

        $validated = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)]]);

        $this->forum->toggleReaction($post, $request->user(), ReactionType::from($validated['type']));

        return back();
    }

    public function report(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('report', $post);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->forum->report($post, $request->user(), $validated['reason']);

        return back()->with('success', 'Thank you. A moderator will look at the post.');
    }

    /**
     * Pin or unpin, lock or unlock.
     */
    public function updateTopic(Request $request, ForumTopic $topic): RedirectResponse
    {
        $this->authorize('moderate', ForumTopic::class);

        $validated = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_pinned', $validated)) {
            $this->forum->setPinned($topic, (bool) $validated['is_pinned']);
        }

        if (array_key_exists('is_locked', $validated)) {
            $this->forum->setLocked($topic, (bool) $validated['is_locked']);
        }

        return back()->with('success', 'The topic was updated.');
    }

    public function destroyTopic(Request $request, ForumTopic $topic): RedirectResponse
    {
        $this->authorize('delete', $topic);

        $this->forum->removeTopic($topic, $request->user());

        return redirect()->route('forum.categories.show', $topic->forum_category_id)->with('success', 'The topic was removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function topicSummary(ForumTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'author' => $topic->author->name,
            'is_pinned' => $topic->is_pinned,
            'is_locked' => $topic->is_locked,
            'replies_count' => $topic->replies_count,
            'last_posted_at' => $topic->last_posted_at?->toIso8601String(),
            'category' => $topic->relationLoaded('category') ? $topic->category->only(['id', 'name']) : null,
        ];
    }

    /**
     * Removed posts keep their place but not their text.
     *
     * @return array<string, mixed>
     */
    private function postSummary(ForumPost $post, User $user, bool $opening, bool $reported): array
    {
        $removed = $post->trashed();
        $reactions = $post->reactions;

        return [
            'id' => $post->id,
            'body' => $removed ? null : $post->body,
            'attachments' => $removed ? [] : $post->attachments->map(fn (Attachment $attachment) => $attachment->summary())->all(),
            'removed' => $removed,
            'is_opening' => $opening,
            'author' => [
                'name' => $post->author->name,
                'role' => $post->author->roles->first()?->name,
            ],
            'created_at' => $post->created_at->toIso8601String(),
            'edited_at' => $post->edited_at?->toIso8601String(),
            'reactions' => collect(ReactionType::cases())->mapWithKeys(fn (ReactionType $type) => [$type->value => [
                'count' => $reactions->where('type', $type)->count(),
                'mine' => $reactions->contains(fn (ForumPostReaction $reaction) => $reaction->type === $type && $reaction->user_id === $user->id),
            ]]),
            'reported' => $reported,
            'can' => $removed ? ['update' => false, 'delete' => false, 'react' => false, 'report' => false] : [
                'update' => $user->can('update', $post),
                // The same rules as ForumPostPolicy::delete and ::report, using what this
                // page already knows instead of a query per post. The actions still go
                // through the policy.
                'delete' => ! $post->topic->trashed() && ($user->can('forum.moderate')
                    || ($post->user_id === $user->id && $user->can('forum.participate') && ! $opening)),
                'react' => $user->can('react', $post),
                'report' => ! $reported && $post->user_id !== $user->id && $user->can('forum.participate'),
            ],
        ];
    }
}
