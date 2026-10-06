<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Realtime channel sign-in (/broadcasting/auth), for signed-in people only.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route middleware from Spatie Laravel Permission, for example
        // ->middleware('permission:students.view').
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Consistent error handling for pages.
         *
         * Validation errors are handled by Inertia: the user is sent back to
         * the form and each field's message appears under it. This hook only
         * replaces error responses with the app's own error page.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            // An expired form, or too many attempts, from inside the app:
            // go back to the page with a message rather than an error screen.
            if (in_array($status, [419, 429], true) && $request->hasHeader('X-Inertia')) {
                return back()->with('error', $status === 419
                    ? 'The page expired. Please try again.'
                    : 'You are doing that too often. Wait a minute, then try again.');
            }

            // "Forbidden", "not found", "expired" and "too many requests" always
            // get the app's error page. Server errors get it only when
            // APP_DEBUG is off, so developers still see the detailed error
            // screen locally and visitors never see technical details.
            $showErrorPage = in_array($status, [403, 404, 419, 429], true)
                || (in_array($status, [500, 503], true) && ! config('app.debug'));

            if ($showErrorPage) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
