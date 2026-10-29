<?php

namespace App\Http\Controllers;

use App\Actions\User\DeleteUserAction;
use App\Actions\User\SaveUserAction;
use App\Enums\RoleEnum;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($q) => $q->role($role))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => (new UserResource($user))->resolve($request));

        $roleOptions = array_map(static fn (RoleEnum $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], RoleEnum::cases());

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roleOptions' => $roleOptions,
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
        ]);
    }

    public function store(StoreUserRequest $request, SaveUserAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return back()->with('success', __('ui.messages.user_saved'));
    }

    public function update(UpdateUserRequest $request, User $user, SaveUserAction $action): RedirectResponse
    {
        $action->execute($request->validated(), $user);

        return back()->with('success', __('ui.messages.user_saved'));
    }

    public function destroy(Request $request, User $user, DeleteUserAction $action): RedirectResponse
    {
        Gate::authorize('delete', $user);

        try {
            $action->execute($request->user(), $user);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('ui.messages.user_deleted'));
    }
}
