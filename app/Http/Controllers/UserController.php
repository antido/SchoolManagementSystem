<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /**
     * List accounts with search, role and status filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'role' => (string) $request->query('role'),
            'status' => (string) $request->query('status'),
        ];

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->whereContains('name', $filters['search'])
                        ->orWhereContains('email', $filters['search']);
                });
            })
            ->when(RoleName::tryFrom($filters['role']), fn ($query, RoleName $role) => $query->role($role->value))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->summary($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roleOptions($request->user()),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => $this->roleOptions($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create(
            $request->safe()->only(['name', 'email', 'password']),
            $request->validated('role'),
            $request->boolean('is_active'),
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', "The account for {$user->name} was created.");
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        $actor = $request->user();

        return Inertia::render('Users/Show', [
            'user' => [
                ...$this->summary($user),
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'can' => [
                'update' => $actor->can('update', $user),
                'changeStatus' => $actor->can('changeStatus', $user),
                'resetPassword' => $actor->can('resetPassword', $user),
                'delete' => $actor->can('delete', $user),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $this->summary($user),
            'roles' => $this->roleOptions($request->user()),
            'isSelf' => $request->user()->is($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->safe()->only(['name', 'email', 'password']));

        if ($request->has('role')) {
            $this->users->assignRole($user, $request->validated('role'));
        }

        if ($request->has('is_active')) {
            $this->users->setActive($user, $request->boolean('is_active'));
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->name}'s account was updated.");
    }

    /**
     * Activate or deactivate an account from the list or details page.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeStatus', $user);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $this->users->setActive($user, $validated['is_active']);

        return back()->with('success', $validated['is_active']
            ? "{$user->name}'s account is active again."
            : "{$user->name}'s account was deactivated. They can no longer sign in.");
    }

    /**
     * Email the user a link to choose a new password.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $status = $this->users->sendPasswordResetLink($user);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', "A password reset link was sent to {$user->email}.")
            : back()->with('error', __($status));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "{$user->name}'s account was deleted.");
    }

    /**
     * The account fields shown on the user screens.
     *
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $role = $user->roles->first()?->name;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'role_label' => $role ? RoleName::from($role)->label() : null,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * The roles this person may hand out. Only a super admin sees "Super Admin".
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(User $actor): array
    {
        return collect(RoleName::cases())
            ->reject(fn (RoleName $role) => $role === RoleName::SuperAdmin
                && ! $actor->hasRole(RoleName::SuperAdmin->value))
            ->map(fn (RoleName $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->all();
    }
}
