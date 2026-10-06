<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Jobs\SendAnnouncement;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's notifications, and announcements sent by staff.
 * People only ever reach their own notifications: every query starts
 * from $request->user()->notifications().
 */
class NotificationController extends Controller
{
    /**
     * The notification centre.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $unreadOnly = $request->query('filter') === 'unread';

        return Inertia::render('Notifications/Index', [
            'notifications' => $user->notifications()
                ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
                ->paginate(20)
                ->withQueryString()
                ->through(fn (DatabaseNotification $notification) => $this->present($notification)),
            'filter' => $unreadOnly ? 'unread' : 'all',
            'can' => ['announce' => $user->can('notifications.send')],
        ]);
    }

    /**
     * The latest few, for the menu in the top bar.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $user->notifications()->limit(8)->get()->map(fn (DatabaseNotification $notification) => $this->present($notification)),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark as read and go to the page it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        // Only paths inside the app, never another site.
        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications are marked as read.');
    }

    public function createAnnouncement(Request $request): Response
    {
        abort_unless($request->user()->can('notifications.send'), 403);

        return Inertia::render('Notifications/Announce', [
            'roles' => array_map(fn (RoleName $role) => ['value' => $role->value, 'label' => $role->label()], RoleName::cases()),
            'classes' => SchoolClass::query()->with('academicYear')->latest('id')->limit(200)->get()
                ->map(fn (SchoolClass $class) => ['value' => $class->id, 'label' => "{$class->name} ({$class->academicYear->name})"]),
        ]);
    }

    /**
     * Send an announcement to everyone, everyone with a role, or a class
     * (its current students and teachers).
     */
    public function sendAnnouncement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send'), 403);

        $validated = $request->validate([
            'audience' => ['required', Rule::in(['everyone', 'role', 'class'])],
            'role' => ['required_if:audience,role', 'nullable', Rule::enum(RoleName::class)],
            'school_class_id' => ['required_if:audience,class', 'nullable', 'integer', 'exists:school_classes,id'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $audience = collect($validated)->only(['audience', 'role', 'school_class_id'])->all();
        $count = SendAnnouncement::recipients($audience)->count();

        // Delivered in the background, so a school-wide announcement does not hold up this request.
        SendAnnouncement::dispatch($audience, $validated['title'], $validated['message'], $request->user()->name);

        return redirect()->route('notifications.index')
            ->with('success', "The announcement is being sent to {$count} ".($count === 1 ? 'person' : 'people').'.');
    }

    /**
     * @return array{id: string, title: string, body: string|null, url: string, icon: string, read: bool, created_at: string, when: string}
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => (string) ($notification->data['title'] ?? 'Notification'),
            'body' => $notification->data['body'] ?? null,
            'url' => route('notifications.open', $notification->id),
            'icon' => (string) ($notification->data['icon'] ?? 'bell'),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
            'when' => $notification->created_at->diffForHumans(),
        ];
    }
}
