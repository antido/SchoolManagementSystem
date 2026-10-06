<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Only the listed user fields are sent to the browser, never the whole
     * model. See resources/js/Types/index.d.ts for the matching types.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                // Limits for file inputs, so the browser can check before uploading.
                'uploads' => [
                    'max_files' => config('school.attachments.max_files'),
                    'max_kilobytes' => config('school.attachments.max_kilobytes'),
                    'extensions' => config('school.attachments.extensions'),
                ],
                // Whether to listen for notifications over Reverb (otherwise the page polls).
                'realtime' => (bool) config('school.realtime'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatarUrl(),
                    'roles' => $user->getRoleNames(),
                ] : null,
                // Lets the interface hide what the user cannot use.
                // The server still checks every request.
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                // The badge on the notification bell.
                'unread_notifications' => fn () => $user ? $user->unreadNotifications()->count() : 0,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
